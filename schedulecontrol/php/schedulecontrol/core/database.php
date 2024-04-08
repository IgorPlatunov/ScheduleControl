<?php
	namespace ScheduleControl\Core;
	use PDO, Throwable, PDOException, DataBaseUpdater;

	final class DataBase
	{
		private static PDO $pdo;
		private static bool $transaction = false;

		public static function Open(string $host, string $user, string $pass, ?int $port = null): void
		{
			self::$pdo = new PDO("mysql:host=$host;charset=utf8mb4".(isset($port) ? ";port=$port" : ""), $user, $pass, array(
				PDO::ATTR_PERSISTENT => true,
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			));
		}

		private static $savedquery = true;
		public static function Query(string $query, array $params = array()): array
		{
			try
			{
				$statement = self::$pdo->prepare($query);
				foreach ($params as $param => $value)
					$statement->bindValue(is_int($param) ? $param + 1 : ":$param", $value);

				$statement->execute();

				return $statement->fetchAll();
			}
			catch (PDOException $ex)
			{
				if (!self::$savedquery) throw $ex;
				
				self::$savedquery = false;

				try
				{
					DataBaseUpdater::Update();

					$result = self::Query($query, $params);
					self::$savedquery = true;

					return $result;
				}
				catch (Throwable $th)
				{
					self::$savedquery = true;

					throw $th;
				}
			}
		}

		public static function MultiQuery(string $query, string $delimiter = ";"): void
		{
			self::Transaction(function() use ($query, $delimiter) {
				foreach (explode($delimiter, $query) as $q)
				{
					$q = trim($q);

					if (strlen($q) > 0) self::Query($q);
				}
			});
		}


		public static function Transaction(callable $func): void
		{
			if (self::$transaction)
				$func();
			else
			{
				self::$pdo->exec("START TRANSACTION");
				self::$transaction = true;

				try
				{
					$func();

					self::$pdo->exec("COMMIT");
					self::$transaction = false;
				}
				catch (Throwable $ex)
				{
					self::$pdo->exec("ROLLBACK");
					self::$transaction = false;

					throw $ex;
				}
			}
		}

		public static function SelectDB(string $dbname): void
		{ if (self::IsDBExists($dbname)) self::$pdo->exec("USE `$dbname`"); }

		public static function IsDBExists(string $dbname): bool
		{
			foreach (self::Query("SHOW DATABASES") as $db)
				if ($db["Database"] == $dbname) return true;

			return false;
		}

		public static function LastInsertID(): int
		{ return self::Query("SELECT LAST_INSERT_ID() AS `id`")[0]["id"]; }

		public static function RowCount(): int
		{ return self::Query("SELECT ROW_COUNT() AS `count`")[0]["count"]; }

		public static function SetForeignKeyCheck(bool $enable): void
		{ self::Query("SET foreign_key_checks = ".($enable ? 1 : 0)); }
	}
?>