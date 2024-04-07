<?php
	namespace ScheduleControl\Core;
	use DateTime;

	abstract class DBTableValue
	{
		public function __construct(
			public DBTableRow $row,
			public string $column,
		) {}

		public function GetColumn(): DBTableColumnInfo
		{ return $this->row->table->GetColumns()[$this->column]; }

		abstract public function GetValue(): mixed;
		abstract public function GetDBValue(): ?string;
		abstract public function GetNiceValue(): string;
	}

	class DBTableScalarValue extends DBTableValue
	{
		public function __construct(
			DBTableRow $row,
			string $column,
			public ?string $value,
		) { parent::__construct($row, $column); }

		public function GetValue(): ?string { return $this->value; }
		public function GetDBValue(): ?string { return $this->value; }
		public function GetNiceValue(): string { return $this->value ?? "???"; }
	}

	class DBTableForeignValue extends DBTableValue
	{
		public array $values = array();

		public function __construct(
			DBTableRow $row,
			string $column,
			public ?string $dbvalue,
		) {
			parent::__construct($row, $column);

			if (isset($dbvalue))
				$this->values = $this->row->table->GetForeignRows($column, $dbvalue);
		}

		public function GetValue(): ?DBTableRow
		{ return isset($this->dbvalue) ? DBTableRegistryCollections::GetRightForeignObject($this)?->GetData() : null; }

		public function GetForeignRows(): array { return $this->values; }
		public function GetDBValue(): ?string { return $this->dbvalue; }
		public function GetNiceValue(): string { return $this->GetValue()?->GetName() ?? "???"; }
	}
?>