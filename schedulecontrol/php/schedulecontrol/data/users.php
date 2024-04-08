<?php
	namespace ScheduleControl\Data;
	use RuntimeException;
	use ScheduleControl\{Core as Core, Config};

	final class UsersControl implements Core\UsersController
	{
		private static Core\DBTable $userstable;
		private static Core\DBTable $rolestable;

		public static function init()
		{
			$userscolumns = array(
				new IntDBTableColumnInfo("ID", "Код", true),
				new TextDBTableColumnInfo("Login", "Логин", 32),
				new TextDBTableColumnInfo("Password", "Пароль", 256),
			);
			self::$userstable = new DBTableColumn(new Core\DBTableInfo("Users", "Пользователи"), $userscolumns, $userscolumns[0], null);

			$rolescolumns = array(
				new ForeignDBTableColumnInfo("User", "Пользователь", new Core\DBTableForeignKeyInfo($userscolumns[0]), true),
				new TextDBTableColumnInfo("Role", "Роль", 32, true),
			);
			self::$rolestable = new DBTableColumn(new Core\DBTableInfo("Roles", "Роли"), $rolescolumns, $rolescolumns[0], null);
		}

		public static function CreateDefaultUserIfNeed(): void
		{
			if (count(self::$rolestable->Select(array("Role" => "Administrator"))) == 0)
			{
				$login = Config::GetParameter("DatabaseInfo", "DefaultUser", "Login");
				$pass = Config::GetParameter("DatabaseInfo", "DefaultUser", "Password");

				self::SaveUser(new Core\User(null, $login, $pass, "Administrator"));
			}
		}

		public static function GetUsersTable(): Core\DBTable { return self::$userstable; }
		public static function GetRolesTable(): Core\DBTable { return self::$rolestable; }

		public static function GetAllUsers(bool $assoc = false): array
		{
			$users = array();

			foreach (self::$userstable->Select() as $data)
			{
				$roles = array();
				foreach (self::$rolestable->Select(array("User" => $data->GetValue("ID"))) as $role)
					$roles[] = $role->GetValue("Role");

				$user = new Core\User($data->GetValue("ID"), $data->GetValue("Login"), $data->GetValue("Password"), $roles, true);

				if ($assoc) $users[$user->GetLogin()] = $user;
				else $users[] = $user;
			}

			return $users;
		}

		public static function GetUser(string $id, bool $login = false): ?Core\User
		{
			$data = self::$userstable->Select($login ? array("Login" => $id) : array("ID" => $id));
			if (count($data) == 0) return null;

			$data = $data[0];

			$roles = array();
			foreach (self::$rolestable->Select(array("User" => $data->GetValue("ID"))) as $role)
				$roles[] = $role->GetValue("Role");

			return new Core\User($data->GetValue("ID"), $data->GetValue("Login"), $data->GetValue("Password"), $roles, true);
		}

		public static function IsUserExists(Core\User $user, bool $login = false): bool
		{
			if ($login)
				return count(self::$userstable->Select(array("Login" => $user->GetLogin()))) > 0;
			
			return $user->GetID() !== null ? count(self::$userstable->Select(array("ID" => $user->GetID()))) > 0 : false;
		}

		public static function SaveUser(Core\User $user): void
		{
			if (self::IsUserExists($user))
				self::$userstable->Update(array("ID" => $user->GetID()), array("Login" => $user->GetLogin(), "Password" => $user->GetPasswordHash()));
			else if ($user->GetID() !== null)
				self::$userstable->Insert(array("ID" => $user->GetID(), "Login" => $user->GetLogin(), "Password" => $user->GetPasswordHash()));
			else
			{
				self::$userstable->Insert(array("Login" => $user->GetLogin(), "Password" => $user->GetPasswordHash()));

				if (Core\DataBase::RowCount() != 1)
					throw new RuntimeException("Не удалось получить идентификатор созданного пользователя (Не удалось создать пользователя?)", 500);

				$user->SetID(Core\DataBase::LastInsertID());
			}

			self::$rolestable->Delete(array("User" => $user->GetID()));

			foreach ($user->GetRoles() as $role)
				self::$rolestable->Insert(array("User" => $user->GetID(), "Role" => $role));
		}

		public static function DeleteUser(Core\User $user): void
		{
			if (!self::IsUserExists($user)) return;

			self::$rolestable->Delete(array("User" => $user->GetID()));
			self::$userstable->Delete(array("ID" => $user->GetID()));
		}
	}

	Core\UserPrivileges::Register("CurriculumWrite", "Учебный план: запись");
	Core\UserPrivileges::Register("CurriculumRead", "Учебный план: чтение");
	Core\UserPrivileges::Register("YearGraphWrite", "График ОП: запись");
	Core\UserPrivileges::Register("YearGraphRead", "График ОП: чтение");
	Core\UserPrivileges::Register("YearLoadWrite", "Нагрузка на год: запись");
	Core\UserPrivileges::Register("YearLoadRead", "Нагрузка на год: чтение");
	Core\UserPrivileges::Register("LoadSubjectLecturerWrite", "Преподаватели нагрузки: запись");
	Core\UserPrivileges::Register("LoadSubjectLecturerRead", "Преподаватели нагрузки: чтение");
	Core\UserPrivileges::Register("SemesterScheduleWrite", "Расписание на семестр: запись");
	Core\UserPrivileges::Register("SemesterScheduleRead", "Расписание на семестр: чтение");
	Core\UserPrivileges::Register("ScheduleWrite", "Расписание: запись");
	Core\UserPrivileges::Register("ScheduleRead", "Расписание: чтение");
	Core\UserPrivileges::Register("Super", "Права суперпользователя");

	Core\UserRoles::Register("CurriculumEditor", "Редактор учебного плана", Core\UserPrivileges::Get("CurriculumWrite", "CurriculumRead"));
	Core\UserRoles::Register("YearGraphEditor", "Редактор графика ОП", Core\UserPrivileges::Get("CurriculumRead", "YearGraphWrite", "YearGraphRead"));
	Core\UserRoles::Register("YearLoadEditor", "Редактор нагрузки на год", Core\UserPrivileges::Get("CurriculumRead", "YearLoadWrite", "YearLoadRead"));
	Core\UserRoles::Register("LoadSubjectLecturersEditor", "Редактор преподавателей нагрузки", Core\UserPrivileges::Get("YearLoadRead","LoadSubjectLecturerWrite", "LoadSubjectLecturerRead"));
	Core\UserRoles::Register("SemesterScheduleEditor", "Редактор расписания на семестр", Core\UserPrivileges::Get("YearGraphRead", "YearLoadRead", "LoadSubjectLecturerRead", "SemesterScheduleWrite", "SemesterScheduleRead"));
	Core\UserRoles::Register("ScheduleEditor", "Редактор расписания", Core\UserPrivileges::Get("SemesterScheduleRead", "ScheduleWrite", "ScheduleRead"));
	Core\UserRoles::Register("ScheduleViewer", "Обозреватель расписания", Core\UserPrivileges::Get("ScheduleRead"));
	Core\UserRoles::Register("Administrator", "Администратор", Core\UserPrivileges::Get("Super"), "CurriculumEditor", "YearGraphEditor", "YearLoadEditor", "LoadSubjectLecturersEditor", "SemesterScheduleEditor", "ScheduleEditor");

	UsersControl::init();
?>