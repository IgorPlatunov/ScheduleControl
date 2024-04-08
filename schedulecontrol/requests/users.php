<?php
	use ScheduleControl\{Core as Core, Data as Data};
	use ScheduleControl\Core\Logs;

	require_once("../php/ajaxrequests.php");

	function GetUserData(Core\User $user): array
	{
		$data = array(
			"id" => $user->GetID(),
			"login" => $user->GetLogin(),
			"passhash" => $user->GetPasswordHash(),
			"roles" => array(),
			"privileges" => array(),
		);

		foreach ($user->GetRoles() as $role)
			$data["roles"][$role] = Core\UserRoles::GetNiceName($role);

		foreach (Core\UserPrivileges::From($user->GetPrivileges()) as $privilege)
			$data["privileges"][$privilege] = Core\UserPrivileges::GetNiceName($privilege);

		return $data;
	}

	AJAXRequest::Proccess(function() {
		$type = AJAXRequest::GetParameter("type");

		if ($type == "all")
		{
			AJAXRequest::CheckUserAccess("Super");

			$users = array();

			foreach (Data\UsersControl::GetAllUsers() as $user)
				$users[] = GetUserData($user);

			AJAXRequest::Response($users);
		}
		else if ($type == "userid")
		{
			AJAXRequest::CheckUserAccess("Super");

			$user = Data\UsersControl::GetUser(AJAXRequest::GetParameter("userid"));
			if (!isset($user)) AJAXRequest::ClientError(1, "Данный пользователь не найден");

			AJAXRequest::Response(GetUserData($user));
		}
		else if ($type == "login")
		{
			AJAXRequest::CheckUserAccess("Super");

			$user = Data\UsersControl::GetUser(AJAXRequest::GetParameter("login"), true);
			if (!isset($user)) AJAXRequest::ClientError(1, "Данный пользователь не найден");

			AJAXRequest::Response(GetUserData($user));
		}
		else if ($type == "self")
			AJAXRequest::Response(GetUserData(Session::CurrentUser()));
		else
			AJAXRequest::ClientError(0, "Неизвестный тип запроса");
	},
	function($data) {
		if (!Session::CurrentUser()->VerifyPassword(AJAXRequest::GetParameter("password")))
			AJAXRequest::ClientError(1, "Не удалось аутентифицировать пользователя (неправильный пароль)", 3);

		$type = AJAXRequest::GetParameter("type");
		if ($type == "create")
		{
			AJAXRequest::CheckUserAccess("Super");

			$user = new Core\User(null, $data["login"], $data["password"], $data["roles"]);

			if ($user->GetLogin() == "")
				AJAXRequest::ClientError(2, "Пользователь не может быть без логина");

			if (Data\UsersControl::IsUserExists($user, true))
				AJAXRequest::ClientError(3, "Пользователь с данным логином уже существует");

			Data\UsersControl::SaveUser($user);

			Logs::Write("Создан новый пользователь ".$user->GetLogin()." (текущий пользователь: ".Session::CurrentUser()->GetLogin().")");

			AJAXRequest::Response(array("id" => $user->GetID()));
		}
		else if ($type == "update")
		{
			if (count($data) == 0) AJAXRequest::ClientError(7, "Нет данных для обработки");

			$userid = AJAXRequest::GetParameter("userid");
			
			if (Session::CurrentUser()->GetID() != $userid)
				AJAXRequest::CheckUserAccess("Super");

			$user = Data\UsersControl::GetUser($userid);
			if (!isset($user)) AJAXRequest::ClientError(4, "Данный пользователь не найден");

			if (isset($data["login"]))
			{
				$user->SetLogin($data["login"]);

				if ($user->GetLogin() == "")
					AJAXRequest::ClientError(2, "Пользователь не может быть без логина");

				if (Data\UsersControl::IsUserExists($user, true))
					AJAXRequest::ClientError(3, "Пользователь с данным логином уже существует");
			}

			if (isset($data["password"]))
				$user->SetPassword($data["password"]);

			if (isset($data["roles"]))
			{
				AJAXRequest::CheckUserAccess("Super");
				
				$super = $user->HasPrivilege("Super");
				
				foreach ($user->GetRoles() as $role)
					$user->RevokeRole($role);

				foreach ($data["roles"] as $role)
					$user->GrantRole($role);
				
				if ($super && !$user->HasPrivilege("Super"))
				{
					$allow = false;

					foreach (Data\UsersControl::GetAllUsers() as $u)
						if ($u->HasPrivilege("Super") && $u->GetID() != $user->GetID())
						{ $allow = true; break; }

					if (!$allow) AJAXRequest::ClientError(5, "Невозможно удалить последнего пользователя с правами суперпользователя");
				}
			}

			Data\UsersControl::SaveUser($user);

			Logs::Write("Обновлены данные пользователя ".$user->GetLogin()." (текущий пользователь: ".Session::CurrentUser()->GetLogin().")");
		}
		else if ($type == "delete")
		{
			$userid = AJAXRequest::GetParameter("userid");

			AJAXRequest::CheckUserAccess("Super");

			$user = Data\UsersControl::GetUser($userid);
			if (!isset($user)) AJAXRequest::ClientError(4, "Данный пользователь не найден");

			if ($user->GetID() == Session::CurrentUser()->GetID())
				AJAXRequest::ClientError(6, "Удаление текущего пользователя не разрешено");

			$allow = false;

			foreach (Data\UsersControl::GetAllUsers() as $u)
				if ($u->HasPrivilege("Super") && $u->GetID() != $user->GetID())
				{ $allow = true; break; }

			if (!$allow) AJAXRequest::ClientError(5, "Невозможно удалить последнего пользователя с привилегией суперпользователя");

			Data\UsersControl::DeleteUser($user);

			Logs::Write("Удалён пользователь ".$user->GetLogin()." (текущий пользователь: ".Session::CurrentUser()->GetLogin().")");
		}
		else
			AJAXRequest::ClientError(0, "Неизвестный тип запроса");
	});
?>