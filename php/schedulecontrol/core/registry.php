<?php
	namespace ScheduleControl\Core;
	use DateTime;

	abstract class Registry
	{
		public function __construct(private mixed $id) {}
		public function GetID(): mixed { return $this->id; }

		abstract public function GetLastChange(): ?RegistryObject;
		abstract public function AddChange(RegistryObject $value);
	}
?>