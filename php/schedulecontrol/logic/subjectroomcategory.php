<?php
	namespace ScheduleControl\Logic;
	use DateTime, RuntimeException;
	use ScheduleControl\{Utils, Core\DBTableRegistryCollections as Collections};

	final class SubjectRoomCategory implements JSONAdaptive, DatabaseAdaptive
	{
		use DatabaseTransaction;

		private array $subjects = array();
		private array $rooms = array();

		public function __construct(private ?DBSubjectRoomCategory $id, private string $name, private string $abbr) {}

		public function GetID(): ?DBSubjectRoomCategory { return $this->id; }
		public function GetName(): string { return $this->name; }
		public function GetAbbreviation(): string { return $this->abbr; }
		public function GetSubjects(): array { return $this->subjects; }
		public function GetRooms(): array { return $this->rooms; }

		public function AddSubject(DBSubject $subject): void
		{ $this->subjects[$subject->GetID()] = $subject; }

		public function AddRoom(DBRoom $room): void
		{ $this->rooms[$room->GetID()] = $room; }

		public function ToJSON(bool $names = false): array
		{
			$json = array(
				"id" => $this->id?->ToJSON($names),
				"name" => $this->name,
				"abbr" => $this->abbr,
				"subjects" => array(),
				"rooms" => array(),
			);

			foreach ($this->subjects as $subject)
				$json["subjects"][] = $subject->ToJSON($names);

			foreach ($this->rooms as $room)
				$json["rooms"][] = $room->ToJSON($names);

			return $json;
		}

		public static function FromJSON(array $data): SubjectRoomCategory
		{
			$obj = new SubjectRoomCategory(
				isset($data["id"]) ? DBSubjectRoomCategory::FromJSON($data["id"]) : null,
				$data["name"],
				$data["abbr"],
			);

			foreach ($data["subjects"] as $sdata)
				if (($subject = DBSubject::FromJSON($sdata))->IsValid())
					$obj->AddSubject($subject);

			foreach ($data["rooms"] as $rdata)
				if (($room = DBRoom::FromJSON($rdata))->IsValid())
					$obj->AddRoom($room);

			return $obj;
		}

		public function SaveToDB(): void
		{
			$colcats = Collections::GetCollection("SubjectRoomCategories");
			$colsubjs = Collections::GetCollection("SubjectRoomCategorySubjects");
			$colrooms = Collections::GetCollection("SubjectRoomCategoryRooms");

			$this->DeleteFromDatabase();

			$insid = $colcats->AddChange(array($this->id?->GetID()), array("Name" => $this->name, "Abbreviation" => $this->abbr));
			if (!isset($this->id))
				if (isset($insid)) $this->id = new DBSubjectRoomCategory($insid);
				else throw new RuntimeException("Не удалось получить идентификатор созданной категории (не удалось создать категорию?)", 500);
			
			foreach ($this->subjects as $sid => $subject)
				if ($subject->IsValid())
					$colsubjs->AddChange(array($this->id->GetID(), $sid), array("Exists" => 1));

			foreach ($this->rooms as $rid => $room)
				if ($room->IsValid())
					$colrooms->AddChange(array($this->id->GetID(), $rid), array("Exists" => 1));
		}

		public function DeleteFromDB(): void
		{
			if (!isset($this->id)) return;

			$colcats = Collections::GetCollection("SubjectRoomCategories");
			$colsubjs = Collections::GetCollection("SubjectRoomCategorySubjects");
			$colrooms = Collections::GetCollection("SubjectRoomCategoryRooms");

			$registry = $colcats->GetRegistry($this->id->GetID());
			if (!isset($registry)) return;

			$registry->AddChange(null);

			foreach ($colsubjs->GetAllLastChanges(array("Category" => $this->id->GetID())) as $subject)
				$colsubjs->AddChange($subject->GetID(), null);

			foreach ($colrooms->GetAllLastChanges(array("Category" => $this->id->GetID())) as $room)
				$colrooms->AddChange($room->GetID(), null);
		}

		public static function GetFromDatabase(mixed $param): ?SubjectRoomCategory
		{
			$colcats = Collections::GetCollection("SubjectRoomCategories");
			$colsubjs = Collections::GetCollection("SubjectRoomCategorySubjects");
			$colrooms = Collections::GetCollection("SubjectRoomCategoryRooms");

			$data = $colcats->GetLastChange($param->GetID());
			if (!isset($data)) return null;

			$obj = new SubjectRoomCategory($param, $data->GetData()->GetValue("Name"), $data->GetData()->GetValue("Abbreviation"));

			foreach ($colsubjs->GetAllLastChanges(array("Category" => $param->GetID())) as $sdata)
				if (($subject = new DBSubject($sdata->GetData()->GetDBValue("Subject")))->IsValid())
					$obj->AddSubject($subject);

			foreach ($colrooms->GetAllLastChanges(array("Category" => $param->GetID())) as $rdata)
				if (($room = new DBRoom($rdata->GetData()->GetDBValue("Room")))->IsValid())
					$obj->AddRoom($room);

			return $obj;
		}
	}
?>