<?php require_once("themes.php"); ?>

<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<link rel="icon" type="image/vnd.microsoft.icon" href="favicon.ico">

		<title>Авторизация в ИС расписания колледжа</title>

		<?php Themes::LoadThemeFile("shared"); ?>
		<?php Themes::LoadThemeFile("authorization"); ?>
	</head>
	<body>
		<div id="welcome">Информационная система<br>расписания колледжа</div>

		<form method="POST">
			<div id="title">Выполните вход</div>

			<input placeholder="Логин" name="login" required type="text">
			<input placeholder="Пароль" name="password" type="password">
			<input type="hidden" name="type" value="login">

			<input type="submit" value="Войти">
		</form>

		<?php if (PageRequest::IsActionVarSet("wrongpassword")) { ?>
			<div id="message-container">
				<div data-type="error">Неверный логин или пароль!</div>
			</div>
		<?php } ?>
	</body>
</html>