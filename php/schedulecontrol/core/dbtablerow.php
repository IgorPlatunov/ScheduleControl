<?php
	namespace ScheduleControl\Core;
	use DateTime, RuntimeException;

	abstract class DBTableRow
	{
		public array $values;

		public function __construct(
			public DBTable $table,
			array $values,
		) {
			$this->values = array();

			foreach ($values as $column => $value)
				if ($this->table->HasColumn($column))
					if ($this->table->IsForeignKey($column))
						$this->values[$column] = new DBTableForeignValue($this, $column, $value);
					else
						$this->values[$column] = new DBTableScalarValue($this, $column, $value);
		}

		abstract public function GetName(): string;
		abstract public function GetFullName(): string;

		public function GetValue(string $column): mixed {
			if (!isset($this->values[$column]))
				throw new RuntimeException("Попытка получить значение неизвестного столбца '$column' таблицы '".$this->table->GetInfo()->GetName()."'", 500);

			return $this->values[$column]->GetValue();
		}

		public function GetDBValue(string $column): string { return $this->values[$column]->GetDBValue(); }
		public function GetNiceValue(string $column): string
		{
			if (!isset($this->values[$column]))
				throw new RuntimeException("Попытка получить значение неизвестного столбца '$column' таблицы '".$this->table->GetInfo()->GetName()."' для вывода", 500);

			return $this->values[$column]->GetNiceValue();
		}
	}
?>