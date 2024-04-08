<?php
	use ScheduleControl\Core\User;
	Use ScheduleControl\Data\UsersControl;

	require_once("schedulecontrol/open.php");

	final class Session
	{
		private static User|false|null $curuser = null;

		public static function Begin(): void
		{ session_start(array("name" => "sessionid", "gc_maxlifetime" => 600)); }

		public static function DropUser(bool $fulldrop = false): never
		{
			if ($fulldrop)
				http_response_code(403);
			else
				header("Location: index.php");
			
			exit;
		}

		public static function CurrentUser(): ?User
		{
			if (!isset(self::$curuser))
				self::$curuser = ($login = self::GetVar("AuthorizedUser")) !== null ? UsersControl::GetUser($login, true) ?? false : false;

			return self::$curuser instanceof User ? self::$curuser : null;
		}

		public static function Login(string $user, string $password): ?User
		{
			$user = UsersControl::GetUser($user, true);
			if (!isset($user) || !$user->VerifyPassword($password)) return null;

			self::SetVar("AuthorizedUser", $user->GetLogin());
			self::$curuser = $user;

			return $user;
		}

		public static function Logout(): void
		{
			self::$curuser = null;
			self::SetVar("AuthorizedUser");
		}

		public static function SetVar(string $var, int|string|bool|null $value = null): void
		{ $_SESSION[$var] = $value; }

		public static function GetVar(string $var, int|string|bool|null $fallback = null): int|string|bool|null
		{ return $_SESSION[$var] ?? $fallback; }

		public static function HasAccess(string ...$names): bool
		{ return self::CurrentUser()?->HasPrivilege(...$names) ?? false; }
	}
?>