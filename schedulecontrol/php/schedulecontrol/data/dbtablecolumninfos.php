<?php
	namespace ScheduleControl\Data;

	use ScheduleControl\{Core as Core, Utils};
	use DateTime;

	final class ForeignDBTableColumnInfo extends Core\DBTableColumnInfo
	{
		public function __construct(string $physname, string $nicename, Core\DBTableForeignKeyInfo $foreign, bool $primary = false)
		{ parent::__construct($physname, $nicename, array(), $primary, $foreign); }

		public function GetInputType(): string { return ""; }
		public function GetInputLimits(): array { return array(); }
		public function GetDBType(): string { return $this->GetForeignInfo()->GetColumn()->GetDBType(); }
		public function GetDBDefault(): string { return $this->GetForeignInfo()->GetColumn()->GetDBDefault(); }
		public function SqlValue(string $value): string { return $this->GetForeignInfo()->GetColumn()->SqlValue($value); }
	}

	final class TextDBTableColumnInfo extends Core\DBTableColumnInfo
	{
		public function __construct(string $physname, string $nicename, private int $limit = 1024, bool $primary = false, array $checks = array())
		{ parent::__construct($physname, $nicename, $checks, $primary); }

		public function GetInputType(): string { return "text"; }
		public function GetInputLimits(): array { return array("maxlength" => $this->limit); }
		public function GetDBType(): string { return "varchar($this->limit)"; }
		public function GetDBDefault(): string { return "''"; }
		public function SqlValue(string $value): string { return $value; }
	}

	final class DateDBTableColumnInfo extends Core\DBTableColumnInfo
	{
		public function __construct(string $physname, string $nicename, bool $primary = false, array $checks = array())
		{ parent::__construct($physname, $nicename, $checks, $primary); }

		public function GetInputType(): string { return "datetime-local"; }
		public function GetInputLimits(): array { return array("step" => "1"); }
		public function GetDBType(): string { return "datetime"; }
		public function GetDBDefault(): string { return "'2000-01-01 00:00:00'"; }
		public function SqlValue(string $value): string { return Utils::SqlDate(new DateTime($value)); }
	}

	final class DateODBTableColumnInfo extends Core\DBTableColumnInfo
	{
		public function __construct(string $physname, string $nicename, bool $primary = false, array $checks = array())
		{ parent::__construct($physname, $nicename, $checks, $primary); }

		public function GetInputType(): string { return "date"; }
		public function GetInputLimits(): array { return array(); }
		public function GetDBType(): string { return "date"; }
		public function GetDBDefault(): string { return "'2000-01-01'"; }
		public function SqlValue(string $value): string { return Utils::SqlDate(new DateTime($value), true); }
	}

	final class BoolDBTableColumnInfo extends Core\DBTableColumnInfo
	{
		public function __construct(string $physname, string $nicename, bool $primary = false)
		{ parent::__construct($physname, $nicename, array(), $primary); }

		public function GetInputType(): string { return "checkbox"; }
		public function GetInputLimits(): array { return array(); }
		public function GetDBType(): string { return "tinyint(1)"; }
		public function GetDBDefault(): string { return "0"; }
		public function SqlValue(string $value): string { return (bool)$value ? "1" : "0"; }
	}

	final class IntDBTableColumnInfo extends Core\DBTableColumnInfo
	{
		public function __construct(string $physname, string $nicename, bool $primary = false, private int $min = -2**31, private int $max = 2**31, array $checks = array())
		{ parent::__construct($physname, $nicename, $checks, $primary); }

		public function GetInputType(): string { return "number"; }
		public function GetInputLimits(): array { return array("min" => $this->min, "max" => $this->max); }
		public function GetDBChecks(): array {
			$checks = $this->checks;
			array_push($checks, [">=", $this->min], ["<=", $this->max]);

			return $checks;
		}
		public function GetDBType(): string { return "int"; }
		public function GetDBDefault(): string { return "0"; }
		public function SqlValue(string $value): string { return (int)$value; }
	}

	final class DecimalDBTableColumnInfo extends Core\DBTableColumnInfo
	{
		public function __construct(string $physname, string $nicename, bool $primary = false, private int $min = -2**31, private int $max = 2**31, private int $digits = 3, private int $decimals = 3, array $checks = array())
		{ parent::__construct($physname, $nicename, array(), $primary); }

		public function GetInputType(): string { return "number"; }
		public function GetInputLimits(): array { return array("min" => $this->min, "max" => $this->max, "step" => 1 / (10 ** $this->decimals)); }
		public function GetDBChecks(): array {
			$checks = $this->checks;
			array_push($checks, [">=", $this->min], ["<=", $this->max]);

			return $checks;
		}
		public function GetDBType(): string { return "decimal(".($this->digits + $this->decimals).", $this->decimals)"; }
		public function GetDBDefault(): string { return "0"; }
		public function SqlValue(string $value): string { return (float)$value; }
	}

	final class TimeDBTableColumnInfo extends Core\DBTableColumnInfo
	{
		public function __construct(string $physname, string $nicename, bool $primary = false)
		{ parent::__construct($physname, $nicename, array(), $primary); }

		public function GetInputType(): string { return "time"; }
		public function GetInputLimits(): array { return array(); }
		public function GetDBType(): string { return "time"; }
		public function GetDBDefault(): string { return "'00:00:00'"; }
		public function SqlValue(string $value): string { return (new DateTime($value))->format("H:i:s"); }
	}
?>