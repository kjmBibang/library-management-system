<?php
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<title>Borrowers</title>

<link rel="stylesheet" href="css/style.css">

</head>

<body>

<nav class="navbar">

<div class="logo">Bryce<span>Library</span></div>

<ul class="nav-links">
<li><a href="dashboard.php">Dashboard</a></li>
<li><a href="books.php">Books</a></li>
<li><a href="borrowers.php">Borrowers</a></li>
<li><a href="login.php" class="login-btn">Logout</a></li>
</ul>

</nav>


<section>

<h1>Borrowers Management</h1>

<button class="primary-btn">+ Register Borrower</button>


<table class="data-table">

<thead>
<tr>
<th>Borrower ID</th>
<th>Name</th>
<th>Email</th>
<th>Contact</th>
<th>Books Borrowed</th>
<th>Actions</th>
</tr>
</thead>

<tbody>

<tr>
<td>BR001</td>
<td>Juan Dela Cruz</td>
<td>juan@example.com</td>
<td>09123456789</td>
<td>2</td>
<td>
<a href="borrower_details.php" class="primary-btn">View</a>
</td>
</tr>

<tr>
<td>BR002</td>
<td>Maria Santos</td>
<td>maria@example.com</td>
<td>09987654321</td>
<td>1</td>
<td>
<a href="borrower_details.php" class="primary-btn">View</a>
</td>
</tr>

</tbody>

</table>



<h2>Register Borrower</h2>

<form action="#" method="POST">

<label>Full Name</label><br>
<input type="text" name="fullname" required>
<br><br>

<label>Email</label><br>
<input type="email" name="email" required>
<br><br>

<label>Contact Number</label><br>
<input type="text" name="contact" required>
<br><br>

<button type="submit" class="primary-btn">Register</button>

</form>


</section>

</body>
</html>