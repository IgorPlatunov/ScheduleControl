<?php
	namespace ScheduleControl;
	use ScheduleControl\Core\DBTableRegistryCollections as Collections;

	final class UserConfigParameter
	{
		public function __construct(private string $name, private string $nicename, private string $type, private mixed $default) {}

		public function GetName(): string { return $this->name; }
		public function GetNiceName(): string { return $this->nicename; }

		public function GetValue(): mixed
		{
			$registry = Collections::GetCollection("Config")->GetRegistry($this->name, true);
			$value = $registry->GetLastChange()?->GetData()->GetValue("Value");

			if (!isset($value))
				$registry->AddChange(array("ParameterName" => $this->nicename, "Value" => $value = $this->default));

			settype($value, $this->type);

			return $value;
		}

		public function SetValue(mixed $value): void
		{
			settype($value, "string");
			Collections::GetCollection("Config")->AddChange(array($this->name), array("ParameterName" => $this->nicename, "Value" => $value));
		}
	}

	final class UserConfig
	{
		private static array $parameters = array();

		public static function GetParameter(string $name): mixed
		{
			$param = self::$parameters[$name] ?? null;
			
			return $param?->GetValue();
		}

		public static function SetParameter(string $name, mixed $value): void
		{
			$param = self::$parameters[$name] ?? null;

			$param?->SetValue($value);
		}

		public static function Initialize()
		{
			$params = array(
				new UserConfigParameter("ScheduleStartDefaultHour", "Номер первого часа первой основной пары", "integer", 1),
				new UserConfigParameter("ScheduleStartReserveHour", "Номер первого часа первой резервной пары (перед основными)", "integer", 0),
				new UserConfigParameter("ScheduleEndDefaultHour", "Номер последнего часа последней допустимой пары в день", "integer", 8),
				new UserConfigParameter("ScheduleExportMinPairs", "Минимальное количество пар в таблице расписания для экспорта", "integer", 5),
				new UserConfigParameter("ScheduleExportGroupsPerRow", "Количество групп на одну строку в таблице расписания для экспорта", "integer", 5),
				new UserConfigParameter("HoursCDPerStudent", "Количество часов на одного студента для защиты КП", "float", 0.75),
				new UserConfigParameter("HoursEPerStudent", "Количество часов на одного студента для экзамена", "float", 0.25),
				new UserConfigParameter("UseAdditionalLecturersLPFraction", "Процент (от 0 до 1) часов ЛПЗ от общего кол-ва, превышение которого добавляет вторичных преподавателей в расписание", "float", 0.75),
			);

			foreach ($params as $parameter)
			{
				self::$parameters[$parameter->GetName()] = $parameter;
				$parameter->GetValue();
			}
		}
	}
?>