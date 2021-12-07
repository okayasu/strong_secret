<?php
/*
	strong_secret
	written by okayasu
	2021/12/07
*/
require "./config.php";

class MyDB extends SQLite3
{
	function __construct()
	{
		$this->open(DATABASEFILE);
		$result = $this->findIdentity(HOSTNAME);
		$this->hostrow = $result->fetchArray();
		if (!$this->hostrow) {
			$result = $this->addIdentity(HOSTNAME);
			header("Location: ./");
			exit;
		}
	}

	function findIdentity($name)
	{
		return $this->query("SELECT * FROM identities WHERE data = x'" . bin2hex($name) . "'");
	}

	function findIdentityById($id)
	{
		$stmt = $this->prepare("SELECT identities.*, m.data AS password, m.id AS shared_secret "
		."FROM identities "
		."LEFT JOIN shared_secret_identity AS j ON identities.id = j.identity "
		."LEFT JOIN shared_secrets AS m ON j.shared_secret = m.id "
		."WHERE identities.id = :id");
		$stmt->bindValue(":id", $id);
		return $stmt->execute()->fetchArray();
	}

	function addIdentity($name)
	{
		$stmt = $this->prepare("INSERT INTO identities (type, data) VALUES (:type, :data)");
		$stmt->bindValue(':type', 2, SQLITE3_INTEGER);
		$stmt->bindValue(':data', $name, SQLITE3_BLOB);
		return $stmt->execute();
	}

	function deleteIdentity($id)
	{
		$stmt = $this->prepare("DELETE FROM identities WHERE id = :id");
		$stmt->bindValue(":id", $id);
		return $stmt->execute();
	}

	function addSecret($password)
	{
		$stmt = $this->prepare("INSERT INTO shared_secrets (type, data) VALUES (:type, :data)");
		$stmt->bindValue(':type', 2, SQLITE3_INTEGER);
		$stmt->bindValue(':data', $password, SQLITE3_BLOB);
		return $stmt->execute();
	}

	function updateSecret($id, $password)
	{
		$stmt = $this->prepare("UPDATE shared_secrets SET data = :data WHERE id = :id");
		$stmt->bindValue(':id', $id, SQLITE3_INTEGER);
		$stmt->bindValue(':data', $password, SQLITE3_BLOB);
		return $stmt->execute();
	}

	function deleteSecret($id)
	{
		$stmt = $this->prepare("DELETE FROM shared_secrets WHERE id = :id");
		$stmt->bindValue(":id", $id);
		return $stmt->execute();
	}

	function addJoin($ide, $sec)
	{
		$stmt = $this->prepare("INSERT INTO shared_secret_identity (shared_secret, identity) VALUES (:sec, :ide)");
		$stmt->bindValue(':ide', $ide, SQLITE3_INTEGER);
		$stmt->bindValue(':sec', $sec, SQLITE3_INTEGER);
		return $stmt->execute();
	}

	function deleteJoinByIdentity($ide)
	{
		$stmt = $this->prepare("DELETE FROM shared_secret_identity WHERE identity = :ide");
		$stmt->bindValue(':ide', $ide, SQLITE3_INTEGER);
		return $stmt->execute();
	}

	function addAccount($name, $password)
	{
		$this->addIdentity($name);
		$ide = $this->lastInsertRowID();
		$this->addSecret($password);
		$sec = $this->lastInsertRowID();
		$this->addJoin($ide, $sec);
		$this->addJoin($this->hostrow, $sec);
	}

	function updateAccount($id, $password)
	{
		$ide = $this->findIdentityById($id);
		$this->updateSecret($ide["shared_secret"], $password);
	}

	function deleteAccount($id)
	{
		$ide = $this->findIdentityById($id);
		$stmt = $this->prepare("SELECT * FROM shared_secret_identity WHERE identity = :ide");
		$stmt->bindValue(':ide', $ide['id']);
		$result = $stmt->execute();
		while($row = $result->fetchArray()) {
			$this->deleteSecret($row['shared_secret']);
		}
		$this->deleteJoinByIdentity($ide['id']);
		$this->deleteIdentity($ide['id']);
	}
}

$db = new MyDB();

if ($_POST["create"]) {
	$result = $db->addAccount($_POST["name"], $_POST["password"]);
	header("Location: ./");
	exit;
}
if ($_POST["update"]) {
	$db->updateAccount($_POST["update"], $_POST["password"]);
	header("Location: ./");
	exit;
}
if ($_POST["delete"]) {
	$db->deleteAccount($_POST["delete"]);
	header("Location: ./");
	exit;
}

?>
<html>
<head>
	<title>strongSwan secrets</title>
	<link rel="stylesheet" type="text/css" href="default.css" media="screen">
</head>
<body>
	<h1>strongswan manage eap secrets</h1>
<?php
if ($_GET["create"]) {
?>
<form method="POST" action="./">
	<table>
		<tr>
			<th>username</th>
			<td><input type="text" name="name" /></td>
		</tr>
		<tr>
			<th>password</th>
			<td><input type="password" name="password" /></td>
		</tr>
	</table>
	<input type="hidden" name="create" value="1" />
	<button type="submit">create</button>
</form>
<?
} else if ($_GET["update"]) {
	$ide = $db->findIdentityById($_GET["update"]);
?>
<form method="POST" action="./">
	<table>
		<tr>
			<th>username</th>
			<td><input type="text" name="name" value="<?php echo $ide["data"] ?>" disabled /></td>
		</tr>
		<tr>
			<th>password</th>
			<td><input type="password" name="password" value="<?php echo $ide["password"] ?>" /></td>
		</tr>
	</table>
	<input type="hidden" name="update" value="<?php echo $ide["id"] ?>" />
	<button type="submit">update</button>
</form>
<?
} else {
?>
<div style='margin-bottom:1em;'>
	<form method="GET" action="./">
		<input type="hidden" name="create" value="1" />
		<button type="submit">create new</button>
	</form>
</div>
<table>
	<thead>
		<tr><th>user</th><th>command</th></tr>
	</thead>
	<tbody>
<?php
	$result = $db->query("SELECT * FROM identities");
	while($row = $result->fetchArray()) {
		if ($row['id'] == $db->hostrow['id']) {
			continue;
		}
?>
	<tr>
		<td><?php echo $row['data']; ?></td>
		<td>
			<form method="GET" action="./">
				<input type="hidden" name="update" value="<?php echo $row["id"] ?>" />
				<button type="submit">update</button>
			</form>
			<form method="POST" action="./">
				<input type="hidden" name="delete" value="<?php echo $row["id"] ?>" />
				<button type="submit">delete</button>
			</form>
		</td>
	</tr>
<?php } ?>
	</tbody>
</table>
<?php } ?>
</body>
</html>