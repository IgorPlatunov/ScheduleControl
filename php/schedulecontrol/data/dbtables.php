<?php
	namespace ScheduleControl\Data;
	use ScheduleControl\Core as Core;

	final class DBTableColumn extends Core\DBTable
	{
		public function __construct(
			Core\DBTableInfo $info,
			array $columns,
			public string|Core\DBTableColumnInfo $namecolumn,
			public null|string|Core\DBTableColumnInfo $fullnamecolumn,
		) { parent::__construct($info, $columns); }

		public function MakeTableRow(array $values): Core\DBTableRow
		{ return new DBTableRowColumn($this, $values, $this->namecolumn, $this->fullnamecolumn ?? $this->namecolumn); }
	}

	final class DBTableConcatName extends Core\DBTable
	{
		public function __construct(
			Core\DBTableInfo $info,
			array $columns,
			public array $concat,
			public string $separator = " ",
			public ?array $concatfull = null,
			public ?string $fullsep = null,
		) { parent::__construct($info, $columns); }

		public function MakeTableRow(array $values): DBTableRowConcatName
		{ return new DBTableRowConcatName($this, $values, $this->concat, $this->separator, $this->concatfull, $this->fullsep); }
	}

	final class DBTableUnique extends Core\DBTable
	{
		public function __construct(Core\DBTableInfo $info, array $columns, private string $rowclass)
		{ parent::__construct($info, $columns); }

		public function MakeTableRow(array $values): Core\DBTableRow
		{ return new $this->rowclass($this, $values); }
	}
?>