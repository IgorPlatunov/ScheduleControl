<?php
	namespace ScheduleControl\Core;

	class DBTableForeignKeyInfo
	{
		private DBTable $table;

		public function __construct(private DBTableColumnInfo $fkeycolumn)
		{ $this->table = $fkeycolumn->GetTable(); }

		public function GetTable(): DBTable { return $this->table; }
		public function GetColumn(): DBTableColumnInfo { return $this->fkeycolumn; }
	}

	abstract class DBTableColumnInfo
	{
		private DBTable $table;

		public function __construct(
			private string $physname,
			private string $nicename,
			protected array $checks = array(),
			private bool $primary = false,
			public ?DBTableForeignKeyInfo $foreign = null) {}

		public function GetName(): string { return $this->physname; }
		public function GetNiceName(): string { return $this->nicename; }
		public function GetDBChecks(): array { return $this->checks; }
		public function IsPrimary(): bool { return $this->primary; }
		public function GetForeignInfo(): ?DBTableForeignKeyInfo { return $this->foreign; }

		public abstract function GetInputType(): string;
		public abstract function GetInputLimits(): array;
		public abstract function GetDBType(): string;
		public abstract function GetDBDefault(): string;
		public abstract function SqlValue(string $value): string;

		public function SetTable(DBTable $table): void { $this->table = $table; }
		public function GetTable(): DBTable { return $this->table; }
	}

	final class DBTableInfo
	{
		private DBTable $table;

		public function __construct(private string $physname, private string $nicename) {}

		public function GetName(): string { return $this->physname; }
		public function GetNiceName(): string { return $this->nicename; }

		public function SetTable(DBTable $table): void { $this->table = $table; }
		public function GetTable(): DBTable { return $this->table; }
	}
?>