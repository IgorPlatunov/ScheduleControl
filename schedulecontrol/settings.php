<?php
	require_once("php/session.php");

	Session::Begin();
	if (Session::CurrentUser() === null) Session::DropUser();

	require_once("php/pagerequests.php");
	require_once("php/themes.php");

	PageRequest::LoadActions("themes");

	require_once("php/scripts.php");
?>

<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<link rel="icon" type="image/vnd.microsoft.icon" href="favicon.ico">

		<title>Настройки аккаунта</title>

		<?php Themes::LoadThemeFile("shared"); ?>
		<?php Themes::LoadThemeFile("header"); ?>
		<?php Themes::LoadThemeFile("settings"); ?>
	</head>
	<body>
		<?php include("php/header.php"); ?>
		
		<main>
			<div id="page-header">
				<span>Настройки <?php echo Session::HasAccess("Super") ? "администратора" : "пользователя"; ?></span>
			</div>
			<div>
				<?php if (Session::HasAccess("Super")) { ?>
				<div>
					<div class="settings-category">Функции администратора</div>
					<div class="settings">
						<div class="setting">
							<label for="super-update-db">Обновить структуру базы данных</label>
							<input type="button" id="super-update-db" value="Выполнить обновление структуры">
						</div>
						<div class="setting">
							<label for="super-clear-old-data">Удалить старые данные регистров</label>
							<input type="button" id="super-clear-old-data" value="Удалить старые версии объектов">
						</div>
					</div>
				</div>
				<?php } ?>
				<div>
					<div class="settings-category">Настройки браузера</div>
					<div class="settings">
						<div id="user-theme" class="setting">
							<label for="user-theme-list">Оформление:</label>
							<form method="POST">
								<select id="user-theme-list" name="theme" value="<?php echo Themes::GetCurrentTheme(); ?>">
									<?php foreach (Themes::GetThemes() as $theme => $name) { ?>
										<option value="<?php echo $theme ?>"><?php echo $name ?></option>
									<?php } ?>
								</select>
								<input type="hidden" name="type" value="theme">
								<input type="submit" value="Подтвердить и обновить">
							</form>
						</div>
					</div>
				</div>
				<div>
					<div class="settings-category">Настройки пользователя</div>
					<div class="settings">
						<?php if (Session::HasAccess("Super")) { ?>
							<div id="user-select" class="setting">
								<label for="user-select-list">Пользователь:</label>
								<select id="user-select-list"></select>
							</div>
						<?php } ?>

						<div id="user-login" class="setting">
							<label for="user-login-input">Логин:</label>
							<input type="text" id="user-login-input">
						</div>

						<div id="user-password" class="setting">
							<label for="user-password-input">Пароль:</label>
							<input type="password" id="user-password-input">

							<input type="checkbox" id="user-password-change">
							<label for="user-password-change">Изменить</label>
						</div>

						<div id="user-roles" class="setting">
							<label for="user-roles-list">Роли:</label>
							<div id="user-roles-list" class="item-list">
							</div>
						</div>

						<div id="user-privileges" class="setting">
							<label for="user-privileges-list">Права:</label>
							<div id="user-privileges-list" class="item-list">
							</div>
						</div>

						<div id="user-save" class="setting">
							<label for="current-password">Подтвердить изменения пользователя</label>
							<input type="password" id="current-password" placeholder="Пароль текущего пользователя" required>
							<div id="user-save-btns">
								<input type="button" id="user-save-btn" value="Сохранить изменения" disabled></input>
								<?php if (Session::HasAccess("Super")) { ?>
									<input type="button" id="user-del-btn" value="Удалить пользователя" disabled></input>
									<input type="button" id="user-new-btn" value="Создать пользователя" disabled></input>
								<?php } ?>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div id="message-container"></div>
		</main>

		<?php JavaScript::LoadFile("settings"); ?>
	</body>
</html>