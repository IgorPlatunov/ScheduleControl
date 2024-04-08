<?php
	namespace ScheduleControl\Core;
	use DateTime;

	class RegistryObject
	{
		public function __construct(
			private Registry $registry,
			private mixed $id,
			private DateTime $date,
			private mixed $data,
		) {}

		public function GetRegistry(): Registry { return $this->registry; }
		public function GetID(): mixed { return $this->id; }
		public function GetDate(): DateTime { return $this->date; }
		public function GetData(): mixed { return $this->data; }
	}
?>