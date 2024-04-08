<?php
	namespace ScheduleControl\Logic;
	use DateTime;

	final class YearActivities implements JSONAdaptive
	{
		private array $activities = array();

		public function GetActivities(): array { return $this->activities; }
		public function AddActivity(?string $id, DBActivity $activity, float $week, float $length, int $semester): void
		{ $this->activities[] = new YearActivity($id, $activity, $week, $length, $semester); }

		public function FindActivity(float $week): ?YearActivity
		{
			foreach ($this->activities as $act)
				if ($week >= $act->GetWeek() && $week < $act->GetWeek() + $act->GetLength())
					return $act;

			return null;
		}

		public function GetActivitiesLengths(): array
		{
			$activities = array();

			foreach ($this->activities as $act)
				$activities[$act->GetActivity()->GetID()] = ($activities[$act->GetActivity()->GetID()] ?? 0) + $act->GetLength();

			return $activities;
		}

		public function ToJSON(bool $names = false): array
		{
			$json = array();

			foreach ($this->activities as $activity)
			{
				$actjson = $activity->ToJSON($names);
				if (isset($actjson)) $json[] = $actjson;
			}

			return $json;
		}

		public static function FromJSON(array $data): YearActivities
		{
			$obj = new YearActivities();

			foreach ($data as $actdata)
				$obj->activities[] = YearActivity::FromJSON($actdata);

			return $obj;
		}
	}

	final class YearActivity implements JSONAdaptive
	{
		public function __construct(
			private ?string $id,
			private DBActivity $activity,
			private float $week,
			private float $length,
			private int $semester,
		) {}

		public function SetID(string $id): void { $this->id = $id; }
		public function GetID(): ?string { return $this->id; }
		public function GetActivity(): DBActivity { return $this->activity; }
		public function GetSemester(): int { return $this->semester; }
		public function GetWeek(): float { return $this->week; }
		public function GetLength(): float { return $this->length; }

		public function ToJSON(bool $names = false): ?array
		{
			if (!$this->activity->IsValid()) return null;

			return array(
				"id" => $this->id,
				"activity" => $this->activity->ToJSON($names),
				"week" => $this->week,
				"length" => $this->length,
				"semester" => $this->semester,
			);
		}

		public static function FromJSON(array $data): YearActivity
		{
			return new YearActivity(
				$data["id"] ?? null,
				DBActivity::FromJSON($data["activity"]),
				$data["week"],
				$data["length"],
				$data["semester"],
			);
		}
	}
?>