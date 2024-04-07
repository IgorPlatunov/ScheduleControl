<?php
	namespace ScheduleControl\Logic;
	use ScheduleControl\{Core\DBTableRegistryCollections as Collections, Utils};
	use DateTime, RuntimeException;

	final class Schedule implements JSONAdaptive, DatabaseAdaptive
	{
		use DatabaseTransaction;

		private array $groups = array();

		public function __construct(private DateTime $date, private DBBells $bells) {}

		public function GetDate(): DateTime { return $this->date; }
		public function GetBellsSchedule(): DBBells { return $this->bells; }
		public function GetGroups(): array { return $this->groups; }

		public function GetGroup(DBGroup $group, bool $create = false): ?GroupSchedule
		{ return $this->groups[$group->GetID()] ?? ($create ? $this->AddGroup($group) : null); }

		public function AddGroup(DBGroup $group): GroupSchedule
		{ return $this->groups[$group->GetID()] = new GroupSchedule(); }

		public function SetGroup(DBGroup $group, GroupSchedule $schedule): void
		{ $this->groups[$group->GetID()] = $schedule; }

		public function ToJSON(bool $names = false): ?array
		{
			if (!$this->bells->IsValid()) return null;

			$json = array(
				"date" => Utils::SqlDate($this->date, true),
				"bells" => $this->bells->ToJSON($names),
				"groups" => array(),
			);

			foreach ($this->groups as $gid => $data)
			{
				if (!($group = new DBGroup($gid))->IsValid()) continue;

				$json["groups"][] = array(
					"group" => $group->ToJSON($names),
					"schedule" => $data->ToJSON($names),
				);
			}

			return $json;
		}

		public static function FromJSON(array $data): Schedule
		{
			$date = new DateTime($data["date"]);
			$date->setTime(0, 0);

			$obj = new Schedule($date, DBBells::FromJSON($data["bells"]));

			foreach ($data["groups"] as $group)
				$obj->SetGroup(DBGroup::FromJSON($group["group"]), GroupSchedule::FromJSON($group["schedule"]));

			return $obj;
		}

		public function SaveToDB(): void
		{
			$this->DeleteFromDatabase();

			$colschedules = Collections::GetCollection("Schedules");
			$colschedhours = Collections::GetCollection("ScheduleHours");
			$colschedacthours = Collections::GetCollection("ScheduleActHours");
			$colschedrooms = Collections::GetCollection("ScheduleHourRooms");
			$date = Utils::SqlDate($this->date);
			
			$colschedules->AddChange($date, array("Bells" => $this->bells->GetID()));
			
			foreach ($this->GetGroups() as $gid => $gdata)
				if (DBGroup::IsValidValue($gid))
					foreach ($gdata->GetHours() as $hour => $hdata)
					{
						if ($hdata instanceof GroupScheduleHourActivity)
							$colschedacthours->AddChange(array($date, $gid, $hour), array("Activity" => $hdata->GetOccupation()->GetID()));
						else if ($hdata instanceof GroupScheduleHourSubject)
							$colschedhours->AddChange(array($date, $gid, $hour), array("Subject" => $hdata->GetOccupation()->GetID()));

						foreach ($hdata->GetLecturerRooms() as $lectroom)
						{
							$lrid = $colschedrooms->AddChange(array($lectroom->GetID()?->GetID()), array(
								"Schedule" => $date,
								"Group" => $gid,
								"Hour" => $hour,
								"Lecturer" => $lectroom->GetLecturer()->GetID(),
								"Room" => $lectroom->GetRoom()->GetID(),
							));

							if ($lectroom->GetID() === null)
								if (isset($lrid)) $lectroom->SetID(new DBScheduleHourRoom($lrid));
								else throw new RuntimeException("Не удалось получить идентификатор связи кабинета, преподавателя и часа в расписании (не удалось создать связь?)", 500);
						}
					}
		}

		public function DeleteFromDB(): void
		{
			$colschedules = Collections::GetCollection("Schedules");
			$colschedhours = Collections::GetCollection("ScheduleHours");
			$colschedacthours = Collections::GetCollection("ScheduleActHours");
			$colschedrooms = Collections::GetCollection("ScheduleHourRooms");
			$date = Utils::SqlDate($this->date);

			$schedule = $colschedules->GetRegistry($date);
			if (!isset($schedule)) return;

			$schedule->AddChange(null);

			foreach ($colschedhours->GetAllLastChanges(array("Schedule" => $date)) as $hour)
				$colschedhours->AddChange($hour->GetID(), null);

			foreach ($colschedacthours->GetAllLastChanges(array("Schedule" => $date)) as $acthour)
				$colschedacthours->AddChange($acthour->GetID(), null);

			foreach ($colschedrooms->GetAllLastChanges(array("Schedule" => $date)) as $room)
				$colschedrooms->AddChange($room->GetID(), null);
		}

		public static function GetFromDatabase(mixed $param): ?Schedule
		{
			$colschedules = Collections::GetCollection("Schedules");
			$colschedhours = Collections::GetCollection("ScheduleHours");
			$colschedacthours = Collections::GetCollection("ScheduleActHours");
			$colschedrooms = Collections::GetCollection("ScheduleHourRooms");

			$date = Utils::SqlDate($param, true);
			$schedule = $colschedules->GetLastChange($date);
			if (!isset($schedule)) return null;

			$bells = new DBBells($schedule->GetData()->GetDBValue("Bells"));
			if (!$bells->IsValid()) return null;

			$schedulehours = $colschedhours->GetAllLastChanges(array("Schedule" => $date));
			$scheduleacthours = $colschedacthours->GetAllLastChanges(array("Schedule" => $date));
			$obj = new Schedule($param, $bells);

			foreach ($schedulehours as $schedulehour)
			{
				$group = new DBGroup($schedulehour->GetData()->GetDBValue("Group"));
				if (!$group->IsValid()) continue;

				$subject = new DBLoadSubject($schedulehour->GetData()->GetDBValue("Subject"));
				if (!$subject->IsValid()) continue;

				$hour = $schedulehour->GetData()->GetValue("Hour");

				$lectrooms = $colschedrooms->GetAllLastChanges(array("Schedule" => $date, "Group" => $group->GetID(), "Hour" => $hour));
				$hobj = new GroupScheduleHourSubject($subject);

				foreach ($lectrooms as $lectroom)
					if (
						($lecturer = new DBLecturer($lectroom->GetData()->GetDBValue("Lecturer")))->IsValid() &&
						($room = new DBRoom($lectroom->GetData()->GetDBValue("Room")))->IsValid()
					)
						$hobj->AddLecturerRoom(new GroupScheduleHourRoom(
							new DBScheduleHourRoom($lectroom->GetData()->GetValue("ID")),
							$lecturer,
							$room,
						));

				if (count($hobj->GetLecturerRooms()) > 0)
					$obj->GetGroup($group, true)->AddHour($hour, $hobj);
			}

			foreach ($scheduleacthours as $scheduleacthour)
			{
				$group = new DBGroup($scheduleacthour->GetData()->GetDBValue("Group"));
				if (!$group->IsValid()) continue;

				$activity = new DBActivity($scheduleacthour->GetData()->GetDBValue("Activity"));
				if (!$activity->IsValid()) continue;

				$hour = $scheduleacthour->GetData()->GetValue("Hour");

				$lectrooms = $colschedrooms->GetAllLastChanges(array("Schedule" => $date, "Group" => $group->GetID(), "Hour" => $hour));
				$hobj = new GroupScheduleHourActivity($activity);

				foreach ($lectrooms as $lectroom)
					if (
						($lecturer = new DBLecturer($lectroom->GetData()->GetDBValue("Lecturer")))->IsValid() &&
						($room = new DBRoom($lectroom->GetData()->GetDBValue("Room")))->IsValid()
					)
						$hobj->AddLecturerRoom(new GroupScheduleHourRoom(
							new DBScheduleHourRoom($lectroom->GetData()->GetValue("ID")),
							$lecturer,
							$room,
						));

				$obj->GetGroup($group, true)->AddHour($hour, $hobj);
			}

			return $obj;
		}
	}

	final class GroupSchedule implements JSONAdaptive
	{
		private array $hours = array();

		public function GetHours(): array { return $this->hours; }
		public function GetHour(int $hour): ?GroupScheduleHour { return $this->hours[$hour] ?? null; }
		public function AddHour(int $hour, GroupScheduleHour $data): void { $this->hours[$hour] = $data; }
		public function RemoveHour(int $hour): void { unset($this->hours[$hour]); }

		public function ToJSON(bool $names = false): array
		{
			$json = array();

			foreach ($this->hours as $hour => $data)
				$json[$hour] = $data->ToJSON($names);

			return $json;
		}

		public static function FromJSON(array $data): GroupSchedule
		{
			$obj = new GroupSchedule();

			foreach ($data as $hour => $hdata)
				$obj->hours[$hour] = GroupScheduleHour::FromJSON($hdata);

			return $obj;
		}
	}

	abstract class GroupScheduleHour implements JSONAdaptive
	{
		private array $lectrooms = array();
		private ?array $priorityinfo = null;

		public function GetLecturerRooms(): array { return $this->lectrooms; }
		public function AddLecturerRoom(GroupScheduleHourRoom $lectroom): void
		{ $this->lectrooms[] = $lectroom; }

		abstract public function GetOccupation(): ?DBValueWrapper;
		abstract public function GetOccupationName(): string;

		public function SetPriorityInfo(array $priorityinfo): void { $this->priorityinfo = $priorityinfo; }
		public function GetPriorityInfo(): ?array { return $this->priorityinfo; }

		public function ToJSON(bool $names = false): array
		{
			$type = match(true)
			{
				$this instanceof GroupScheduleHourSubject => 0,
				$this instanceof GroupScheduleHourActivity => 1,
				default => 2,
			};

			$json = array(
				"lectrooms" => array(),
				"type" => $type,
				"occupation" => $this->GetOccupation()?->ToJSON($names),
				"priorityinfo" => $this->priorityinfo,
			);

			foreach ($this->lectrooms as $lectroom)
				$json["lectrooms"][] = $lectroom->ToJSON($names);

			if ($names) $json["oname"] = $this->GetOccupationName();

			return $json;
		}

		public static function FromJSON(array $data): GroupScheduleHour
		{
			$obj = match((int)$data["type"])
			{
				0 => new GroupScheduleHourSubject(DBLoadSubject::FromJSON($data["occupation"])),
				1 => new GroupScheduleHourActivity(DBActivity::FromJSON($data["occupation"])),
				2 => new GroupScheduleHourEmpty(),
			};

			foreach ($data["lectrooms"] as $lrdata)
				$obj->AddLecturerRoom(GroupScheduleHourRoom::FromJSON($lrdata));

			return $obj;
		}
	}

	final class GroupScheduleHourRoom implements JSONAdaptive
	{
		public function __construct(
			private ?DBScheduleHourRoom $id,
			private DBLecturer $lecturer,
			private DBRoom $room,
		) {}

		public function SetID(DBScheduleHourRoom $id) { $this->id = $id; }
		public function GetID(): ?DBScheduleHourRoom { return $this->id; }
		public function GetLecturer(): DBLecturer { return $this->lecturer; }
		public function GetRoom(): DBRoom { return $this->room; }

		public function ToJSON(bool $names = false): array
		{
			return array(
				"id" => $this->id?->ToJSON($names),
				"lecturer" => $this->lecturer->ToJSON($names),
				"room" => $this->room->ToJSON($names),
			);
		}

		public static function FromJSON(array $data): GroupScheduleHourRoom
		{
			return new GroupScheduleHourRoom(
				isset($data["id"]) ? DBScheduleHourRoom::FromJSON($data["id"]) : null,
				DBLecturer::FromJSON($data["lecturer"]),
				DBRoom::FromJSON($data["room"]),
			);
		}
	}

	final class GroupScheduleHourSubject extends GroupScheduleHour
	{
		public function __construct(private DBLoadSubject $subject) {}

		public function GetOccupation(): DBLoadSubject { return $this->subject; }
		public function GetOccupationName(): string { return $this->subject->GetValue()->GetName(); }
	}

	final class GroupScheduleHourActivity extends GroupScheduleHour
	{
		public function __construct(private DBActivity $activity) {}

		public function GetOccupation(): DBActivity { return $this->activity; }
		public function GetOccupationName(): string
		{
			$activity = $this->activity->GetValue();
			return $activity->GetValue("Abbreviation")." (".$activity->GetValue("Designation").")";
		}
	}

	final class GroupScheduleHourEmpty extends GroupScheduleHour
	{
		public function GetOccupation(): ?DBValueWrapper { return null; }
		public function GetOccupationName(): string { return "-"; }
	}
?>