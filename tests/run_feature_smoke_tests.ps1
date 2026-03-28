param(
    [string]$BaseUrl = "http://localhost/library-management-system",
    [string]$MysqlExe = "C:/xampp/mysql/bin/mysql.exe",
    [string]$DbName = "bryce_library",
    [bool]$ResetDatabase = $true
)

$ErrorActionPreference = "Stop"

$script:Passed = 0
$script:Failed = 0
$script:FailedTests = New-Object System.Collections.Generic.List[string]

function Assert-True {
    param(
        [bool]$Condition,
        [string]$Message
    )

    if (-not $Condition) {
        throw $Message
    }
}

function Run-Test {
    param(
        [string]$Name,
        [scriptblock]$Action
    )

    try {
        & $Action
        $script:Passed++
        Write-Host "[PASS] $Name" -ForegroundColor Green
    } catch {
        $script:Failed++
        $script:FailedTests.Add("$Name :: $($_.Exception.Message)")
        Write-Host "[FAIL] $Name :: $($_.Exception.Message)" -ForegroundColor Red
    }
}

function Invoke-AppRequest {
    param(
        [ValidateSet("GET", "POST")]
        [string]$Method,
        [string]$Path,
        [Microsoft.PowerShell.Commands.WebRequestSession]$Session,
        [hashtable]$Body = $null
    )

    $uri = $BaseUrl.TrimEnd('/') + '/' + $Path.TrimStart('/')

    $request = @{
        Uri                = $uri
        Method             = $Method
        UseBasicParsing    = $true
        MaximumRedirection = 10
        ErrorAction        = "Stop"
    }

    if ($null -ne $Session) {
        $request.WebSession = $Session
    }

    if ($null -ne $Body) {
        $request.Body = $Body
    }

    return Invoke-WebRequest @request
}

function Invoke-DbCall {
    param([string]$Sql)

    $output = & $MysqlExe -h 127.0.0.1 -P 3306 -u root -D $DbName --batch --skip-column-names -e $Sql
    if ($LASTEXITCODE -ne 0) {
        throw "MySQL call failed for SQL: $Sql"
    }

    return $output
}

$projectRoot = Resolve-Path (Join-Path $PSScriptRoot "..")
$seedPath = Resolve-Path (Join-Path $projectRoot "seed-file/seed.sql")

if ($ResetDatabase) {
    Write-Host "Resetting database from seed-file/seed.sql..." -ForegroundColor Cyan
    $seedForMysql = ($seedPath.Path -replace '\\', '/')
    & $MysqlExe -h 127.0.0.1 -P 3306 -u root -e "SOURCE $seedForMysql"
    if ($LASTEXITCODE -ne 0) {
        throw "Database reset failed"
    }
}

$stamp = Get-Date -Format "yyyyMMddHHmmss"
$rand = Get-Random -Minimum 100 -Maximum 999
$username = "smoke_user_${stamp}_${rand}"
$password = "P@ssw0rd!${rand}"
$bookTitle = "Smoke Book ${stamp}-${rand}"
$bookAuthor = "Smoke Author"
$bookCategory = "Smoke Category ${stamp}"
$borrowerEmail = "smoke.${stamp}.${rand}@example.com"
$borrowerName = "Smoke Borrower ${stamp}"
$borrowerContact = "0917${stamp.Substring($stamp.Length - 7)}"

$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

Run-Test "Public login page reachable" {
    $response = Invoke-AppRequest -Method GET -Path "login.php" -Session $null
    Assert-True ($response.StatusCode -eq 200) "Expected HTTP 200"
}

Run-Test "Protected page redirects when unauthenticated" {
    $response = Invoke-AppRequest -Method GET -Path "dashboard.php" -Session $null
    $finalUrl = $response.BaseResponse.ResponseUri.AbsoluteUri
    Assert-True ($finalUrl -like "*login.php*") "Expected redirect to login, got: $finalUrl"
}

Run-Test "Signup success" {
    $response = Invoke-AppRequest -Method POST -Path "handlers/auth/process_signup.php" -Session $null -Body @{
        username = $username
        password = $password
        role     = "staff"
    }
    $finalUrl = $response.BaseResponse.ResponseUri.AbsoluteUri
    Assert-True ($finalUrl -like "*signup.php?success=1*") "Expected signup success redirect, got: $finalUrl"
}

Run-Test "Signup duplicate username blocked" {
    $response = Invoke-AppRequest -Method POST -Path "handlers/auth/process_signup.php" -Session $null -Body @{
        username = $username
        password = $password
        role     = "staff"
    }
    $finalUrl = $response.BaseResponse.ResponseUri.AbsoluteUri
    Assert-True ($finalUrl -like "*signup.php?error=exists*") "Expected duplicate error redirect, got: $finalUrl"
}

Run-Test "Login fails with wrong password" {
    $response = Invoke-AppRequest -Method POST -Path "handlers/auth/process_login.php" -Session $null -Body @{
        username = $username
        password = "bad-password"
    }
    $finalUrl = $response.BaseResponse.ResponseUri.AbsoluteUri
    Assert-True ($finalUrl -like "*login.php?error=invalid*") "Expected invalid login redirect, got: $finalUrl"
}

Run-Test "Login success" {
    $response = Invoke-AppRequest -Method POST -Path "handlers/auth/process_login.php" -Session $session -Body @{
        username = $username
        password = $password
    }
    $finalUrl = $response.BaseResponse.ResponseUri.AbsoluteUri
    Assert-True ($finalUrl -like "*dashboard.php*") "Expected dashboard redirect, got: $finalUrl"
}

Run-Test "Books page loads while authenticated" {
    $response = Invoke-AppRequest -Method GET -Path "books.php" -Session $session
    Assert-True ($response.StatusCode -eq 200) "Expected HTTP 200"
}

Run-Test "Book add validation catches invalid copies" {
    $response = Invoke-AppRequest -Method POST -Path "handlers/books/process_book_add.php" -Session $session -Body @{
        title            = "Bad Book"
        author           = "Bad Author"
        category         = "Bad Category"
        year_published   = 2026
        total_copies     = 1
        available_copies = 2
    }
    $finalUrl = $response.BaseResponse.ResponseUri.AbsoluteUri
    Assert-True ($finalUrl -like "*book_add.php?error=invalid*") "Expected invalid add redirect, got: $finalUrl"
}

Run-Test "Book add success" {
    $response = Invoke-AppRequest -Method POST -Path "handlers/books/process_book_add.php" -Session $session -Body @{
        title            = $bookTitle
        author           = $bookAuthor
        category         = $bookCategory
        year_published   = 2026
        total_copies     = 2
        available_copies = 2
    }
    $finalUrl = $response.BaseResponse.ResponseUri.AbsoluteUri
    Assert-True ($finalUrl -like "*books.php?book_added=1*") "Expected add success redirect, got: $finalUrl"
}

$script:bookId = 0
Run-Test "Resolve added book id" {
    $rows = Invoke-DbCall -Sql "CALL sp_book_search('$bookTitle', NULL, 1, 0);"
    $first = $rows | Select-Object -First 1
    Assert-True (-not [string]::IsNullOrWhiteSpace($first)) "Book search did not return rows"

    $parts = $first -split "`t"
    $script:bookId = [int]$parts[0]
    Assert-True ($script:bookId -gt 0) "Invalid book id from search"
}

Run-Test "Book edit validation catches invalid year" {
    $response = Invoke-AppRequest -Method POST -Path "handlers/books/process_book_edit.php" -Session $session -Body @{
        book_id          = $script:bookId
        title            = "$bookTitle Edit"
        author           = "$bookAuthor Edit"
        category         = $bookCategory
        year_published   = 10000
        total_copies     = 2
        available_copies = 2
    }
    $finalUrl = $response.BaseResponse.ResponseUri.AbsoluteUri
    Assert-True ($finalUrl -like "*book_edit.php?id=$script:bookId&error=invalid_year*") "Expected invalid year redirect, got: $finalUrl"
}

Run-Test "Book edit success" {
    $response = Invoke-AppRequest -Method POST -Path "handlers/books/process_book_edit.php" -Session $session -Body @{
        book_id          = $script:bookId
        title            = "$bookTitle Updated"
        author           = "$bookAuthor Updated"
        category         = $bookCategory
        year_published   = 2025
        total_copies     = 2
        available_copies = 2
    }
    $finalUrl = $response.BaseResponse.ResponseUri.AbsoluteUri
    Assert-True ($finalUrl -like "*books.php?book_updated=1*") "Expected edit success redirect, got: $finalUrl"
}

Run-Test "Borrower register validation catches invalid email" {
    $response = Invoke-AppRequest -Method POST -Path "handlers/borrowers/process_borrower_register.php" -Session $session -Body @{
        full_name      = "Invalid Borrower"
        email          = "not-an-email"
        contact_number = "09171234567"
    }
    $finalUrl = $response.BaseResponse.ResponseUri.AbsoluteUri
    Assert-True ($finalUrl -like "*borrowers.php?error=invalid*") "Expected invalid borrower redirect, got: $finalUrl"
}

Run-Test "Borrower register success" {
    $response = Invoke-AppRequest -Method POST -Path "handlers/borrowers/process_borrower_register.php" -Session $session -Body @{
        full_name      = $borrowerName
        email          = $borrowerEmail
        contact_number = $borrowerContact
    }
    $finalUrl = $response.BaseResponse.ResponseUri.AbsoluteUri
    Assert-True ($finalUrl -like "*borrowers.php?success=borrower_registered*") "Expected borrower success redirect, got: $finalUrl"
}

$script:borrowerId = 0
Run-Test "Resolve borrower id" {
    $rows = Invoke-DbCall -Sql "CALL sp_borrower_search('$borrowerEmail', 1, 0);"
    $first = $rows | Select-Object -First 1
    Assert-True (-not [string]::IsNullOrWhiteSpace($first)) "Borrower search did not return rows"

    $parts = $first -split "`t"
    $script:borrowerId = [int]$parts[0]
    Assert-True ($script:borrowerId -gt 0) "Invalid borrower id from search"
}

Run-Test "Borrow validation catches past due date" {
    $past = (Get-Date).AddHours(-2).ToString("yyyy-MM-ddTHH:mm")
    $response = Invoke-AppRequest -Method POST -Path "handlers/transactions/process_borrow.php" -Session $session -Body @{
        book_id      = $script:bookId
        borrower_id  = $script:borrowerId
        due_date     = $past
    }
    $finalUrl = $response.BaseResponse.ResponseUri.AbsoluteUri
    Assert-True ($finalUrl -like "*transactions.php?error=borrow_invalid_due*") "Expected invalid due date redirect, got: $finalUrl"
}

Run-Test "Borrow success" {
    $future = (Get-Date).AddDays(7).ToString("yyyy-MM-ddTHH:mm")
    $response = Invoke-AppRequest -Method POST -Path "handlers/transactions/process_borrow.php" -Session $session -Body @{
        book_id      = $script:bookId
        borrower_id  = $script:borrowerId
        due_date     = $future
    }
    $finalUrl = $response.BaseResponse.ResponseUri.AbsoluteUri
    Assert-True ($finalUrl -like "*transactions.php?success=borrowed*") "Expected borrow success redirect, got: $finalUrl"
}

$script:transactionId = 0
Run-Test "Resolve active transaction id" {
    $rows = Invoke-DbCall -Sql "CALL sp_transaction_active_list(5, 0);"
    $first = $rows | Select-Object -First 1
    Assert-True (-not [string]::IsNullOrWhiteSpace($first)) "No active transaction rows found"

    $parts = $first -split "`t"
    $script:transactionId = [int]$parts[0]
    Assert-True ($script:transactionId -gt 0) "Invalid transaction id from active list"
}

Run-Test "Book delete blocked while active transaction exists" {
    $response = Invoke-AppRequest -Method POST -Path "handlers/books/process_book_delete.php" -Session $session -Body @{
        book_id = $script:bookId
    }
    $finalUrl = $response.BaseResponse.ResponseUri.AbsoluteUri
    Assert-True ($finalUrl -like "*books.php?error=cannot_delete_active*") "Expected active transaction delete block, got: $finalUrl"
}

Run-Test "Return validation catches missing transaction" {
    $returnDate = (Get-Date).ToString("yyyy-MM-ddTHH:mm")
    $response = Invoke-AppRequest -Method POST -Path "handlers/transactions/process_return.php" -Session $session -Body @{
        transaction_id = 99999999
        return_date    = $returnDate
    }
    $finalUrl = $response.BaseResponse.ResponseUri.AbsoluteUri
    Assert-True ($finalUrl -like "*transactions.php?error=return_reference*") "Expected return reference error, got: $finalUrl"
}

Run-Test "Return success" {
    $returnDate = (Get-Date).AddHours(1).ToString("yyyy-MM-ddTHH:mm")
    $response = Invoke-AppRequest -Method POST -Path "handlers/transactions/process_return.php" -Session $session -Body @{
        transaction_id = $script:transactionId
        return_date    = $returnDate
    }
    $finalUrl = $response.BaseResponse.ResponseUri.AbsoluteUri
    Assert-True ($finalUrl -like "*transactions.php?success=returned*") "Expected return success redirect, got: $finalUrl"
}

Run-Test "Book delete succeeds after return" {
    $response = Invoke-AppRequest -Method POST -Path "handlers/books/process_book_delete.php" -Session $session -Body @{
        book_id = $script:bookId
    }
    $finalUrl = $response.BaseResponse.ResponseUri.AbsoluteUri
    Assert-True ($finalUrl -like "*books.php?book_deleted=1*") "Expected delete success redirect, got: $finalUrl"
}

Run-Test "Logout success" {
    $response = Invoke-AppRequest -Method GET -Path "handlers/auth/logout.php" -Session $session
    $finalUrl = $response.BaseResponse.ResponseUri.AbsoluteUri
    Assert-True ($finalUrl -like "*login.php*") "Expected redirect to login after logout, got: $finalUrl"
}

Run-Test "Protected page blocked after logout" {
    $response = Invoke-AppRequest -Method GET -Path "dashboard.php" -Session $session
    $finalUrl = $response.BaseResponse.ResponseUri.AbsoluteUri
    Assert-True ($finalUrl -like "*login.php*") "Expected redirect to login after logout, got: $finalUrl"
}

Write-Host ""
Write-Host "Test Summary" -ForegroundColor Cyan
Write-Host "Passed: $script:Passed"
Write-Host "Failed: $script:Failed"

if ($script:Failed -gt 0) {
    Write-Host ""
    Write-Host "Failed Tests:" -ForegroundColor Yellow
    foreach ($failure in $script:FailedTests) {
        Write-Host "- $failure"
    }
    exit 1
}

exit 0
