<?php
	namespace ScheduleControl\Core;
	use DateTime;

	abstract class RegistryCollection
	{
		abstract public function GetRegistries(): array;
		abstract public function GetAllLastChanges(): array;
		abstract public function GetRegistry(string $id, bool $create = false): ?Registry;

		abstract public function GetLastChange(string $id): ?RegistryObject;
		abstract public function AddChange(string $id, RegistryObject $value);

		public function IsRegistryExists(string $id): bool
		{ return $this->GetRegistry($id) != null; }
	}
?>