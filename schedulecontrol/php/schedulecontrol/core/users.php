<?php
    namespace ScheduleControl\Core;

	final class UserPrivileges
	{
		private static array $privs = array();
		private static array $names = array();
		private static int $nextval = 1;

		public static function Register(string $name, string $nicename): void
		{
			if (!isset(self::$privs[$name]))
			{
				self::$privs[$name] = self::$nextval;
				self::$names[$name] = $nicename;
				self::$nextval *= 2;
			}
		}

		public static function GetNiceName(string $name): ?string { return self::$names[$name] ?? null; }

		public static function GetPrivileges(): array
		{
			$privileges = array();
			foreach (self::$privs as $priv => $value) $privileges[] = $priv;

			return $privileges;
		}

		public static function Get(string ...$names): int
		{
			$val = 0;

			foreach ($names as $name)
				if (isset(self::$privs[$name]))
					$val += self::$privs[$name];

			return $val;
		}

		public static function HasAll(int $privileges, string ...$names): bool
		{ return ($privileges & ($privs = self::Get(...$names))) == $privs; }

		public static function HasAny(int $privileges, string ...$names): bool
		{ return ($privileges & self::Get(...$names)) > 0; }

		public static function From(int $privileges): array
		{
			$names = array();

			foreach (self::$privs as $name => $priv)
				if (($priv & $privileges) > 0)
					$names[] = $name;

			return $names;
		}
	}

	final class UserRoles
	{
		private static array $roles = array();
		private static array $names = array();

		public static function Register(string $role, string $nicename, int $privileges, string ...$from): void
		{
			$fromprivs = 0;

			foreach ($from as $frole)
				if (isset(self::$roles[$frole]))
					$fromprivs |= self::$roles[$frole];

			self::$roles[$role] = $fromprivs | $privileges;
			self::$names[$role] = $nicename;
		}

		public static function GetNiceName(string $role): ?string { return self::$names[$role] ?? null; }

		public static function GetRoles(bool $privileges = false): array
		{ 
			if ($privileges) return self::$roles;
			
			$roles = array();
			foreach (self::$roles as $role => $privs) $roles[] = $role;

			return $roles;
		}

		public static function GetPrivileges(string $role): int { return self::$roles[$role] ?? 0; }

		public static function HasPrivilege(string $role, string ...$names): bool
		{ return UserPrivileges::HasAll(self::$roles[$role] ?? 0, ...$names); }

		public static function HasPrivileges(string $role, int $privileges): bool
		{ return UserPrivileges::HasAll(self::$roles[$role] ?? 0, ...UserPrivileges::From($privileges)); }
	}

	final class User
	{
		private string $passhash;
		private array $roles = array();

		public function __construct(private ?string $id, private string $login, string $password, array|string $roles, bool $passhash = false)
		{
			if ($passhash) $this->passhash = $password;
			else $this->SetPassword($password);

			if (is_string($roles)) $this->roles[] = $roles;
			else $this->roles = $roles;
		}

		public function GetID(): ?string { return $this->id; }
		public function GetLogin(): string { return $this->login; }
		public function GetPasswordHash(): string { return $this->passhash; }
		public function GetRoles(): array { return $this->roles; }

		public function SetID(string $id): void { $this->id = $id; }
		public function SetLogin(string $login): void { $this->login = $login; }
		public function SetPassword(string $password): void { $this->passhash = password_hash($password, PASSWORD_DEFAULT); }
		public function SetPasswordHash(string $passhash): void { $this->passhash = $passhash; }
		public function GrantRole(string $role): void { if (array_search($role, $this->roles) === false) $this->roles[] = $role; }
		public function RevokeRole(string $role): void { if (($index = array_search($role, $this->roles)) !== false) array_splice($this->roles, $index, 1); }

		public function VerifyPassword(string $password): bool {
			if ($this->passhash == "" && $password == "") return true;

			return password_verify($password, $this->passhash);
		}

		public function GetPrivileges(): int
		{
			$privs = 0;

			foreach ($this->roles as $role)
				$privs |= UserRoles::GetPrivileges($role);

			return $privs;
		}

		public function HasPrivileges(int $privileges): bool
		{
			foreach ($this->roles as $role)
				if (UserRoles::HasPrivileges($role, $privileges))
					return true;

			return false;
		}

		public function HasPrivilege(string ...$names): bool
		{
			foreach ($this->roles as $role)
			 if (UserRoles::HasPrivileges($role, UserPrivileges::Get(...$names)))
			 	return true;
			
			return false;
		}
	}

	interface UsersController
	{
		public static function GetAllUsers(bool $assoc = false): array;
		public static function GetUser(string $id, bool $login = false): ?User;
		public static function IsUserExists(User $user, bool $login = false): bool;

		public static function SaveUser(User $user): void;
		public static function DeleteUser(User $user): void;
	}
?>