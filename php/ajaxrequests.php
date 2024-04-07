<?php
	use ScheduleControl\{Core, Utils};

	require_once("session.php");

	final class AJAXRequestClientError extends RuntimeException
	{
		public function __construct(string $message, int $code, private int $httpcode = 400)
		{ parent::__construct($message, $code); }

		public function GetHTTPResponseCode(): int { return $this->httpcode; }
	}

	final class AJAXRequest
	{
		public static function GetParameter(string $name, bool $required = true): ?string
		{
			$params = match($_SERVER["REQUEST_METHOD"])
			{
				"POST" => $_POST,
				"GET" => $_GET,
				default => $_REQUEST,
			};

			$param = $params[$name] ?? null;
			if ($required && !isset($param)) self::ClientError(-10, "Требуемый параметр `$name` не установлен");

			return $param;
		}

		public static function GetBoolParameter(string $name, bool $required = true): ?bool
		{
			$param = self::GetParameter($name, $required);
			if ($param === "") self::ClientError(-11, "Параметр `$name` пустой (ожидалось логическое значение)");

			return $param;
		}

		public static function GetIntParameter(string $name, bool $required = true): ?int
		{
			$param = self::GetParameter($name, $required);
			if ($param === "") self::ClientError(-12, "Параметр `$name` пустой (ожидалось целое число)");

			return $param;
		}

		public static function GetFloatParameter(string $name, bool $required = true): ?float
		{
			$param = self::GetParameter($name, $required);
			if ($param === "") self::ClientError(-13, "Параметр `$name` пустой (ожидалось вещественное число)");

			return $param;
		}

		public static function GetDateTimeParameter(string $name, bool $required = true): ?DateTime
		{
			$param = self::GetParameter($name, $required);
			if ($param === "") self::ClientError(-14, "Параметр `$name` пустой (ожидался объект даты/времени)");

			return isset($param) ? new DateTime($param) : null;
		}

		public static function ClientError(int $code, string $message, int $httpcode = 0): void
		{ throw new AJAXRequestClientError($message, $code, 400 + $httpcode % 100); }

		public static function ServerError(string $message): void
		{
			Core\Logs::Write("Серверная ошибка при обработке запроса клиента: ".$message." (пользователь ".Session::CurrentUser()->GetLogin().")");
			throw new RuntimeException($message, 0);
		}

		private static function ErrorResponse(string $message, int $code, int $httpcode): never
		{
			http_response_code($httpcode);
			exit(json_encode(array("code" => $code, "message" => $message)));
		}

		public static function Response(array $json = array()): never
		{
			$response = json_encode($json);
			if ($response === false) self::ServerError("Не удалось сформировать ответ на запрос");

			exit($response);
		}

		public static function Proccess(?callable $get = null, ?callable $post = null): never
		{
			try
			{
				$method = $_SERVER["REQUEST_METHOD"];

				if ($method == "GET" && isset($get))
				{
					Session::Begin();
					self::CheckUserAccess();

					$get();

					self::ServerError("Сервер не отправил ответ на запрос");
				}
				else if ($method == "POST" && isset($post))
				{
					$data = json_decode(self::GetParameter("post"), true);
					if (!isset($data)) self::ClientError(-3, "Некорректный формат данных, отправленный клиентом для обработки");
	
					Session::Begin();
					self::CheckUserAccess();
	
					$post($data);
	
					self::Response();
				}
				
				self::ClientError(-4, "Неизвестный метод запроса");
			}
			catch (AJAXRequestClientError $err)
			{ self::ErrorResponse($err->getMessage(), $err->getCode(), $err->GetHTTPResponseCode()); }
			catch (Throwable $ex)
			{ self::ErrorResponse($ex->getMessage(), is_int($ex->getCode()) ? $ex->getCode() : 0, 500); }
		}

		public static function CheckUserAccess(string ...$names): void
		{
			if (($user = Session::CurrentUser()) === null)
				self::ClientError(-1, "Пользователь не авторизован!", 3);

			if (count($names) > 0 && !$user->HasPrivilege(...$names))
			{
				$errprivs = array_map(function($el) { return "'".Core\UserPrivileges::GetNiceName($el)."'"; }, $names);

				self::ClientError(-2, "Попытка использовать функционал, для которого пользователь не обладает нужными правами (требуется: ".Utils::ConcatArray($errprivs, ", ").")", 3);
			}
		}
	}
?>