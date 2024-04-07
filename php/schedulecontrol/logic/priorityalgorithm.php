<?php
	namespace ScheduleControl\Logic;
	use DateTime;

	abstract class PriorityAlgorithmCriterion
	{
		private float $modifier = 1;

		abstract public function GetName(): string;
		abstract public function GetDescription(): string;
		abstract public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool;
		abstract public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null;

		public function GetModifierRange(): ?array { return [0, 5]; }
		public function GetModifier(): float { return $this->modifier; }
		public function SetModifier(float $modifier): void { $this->modifier = $modifier; }
	}

	final class PriorityAlgorithmCriterionData
	{
		private PriorityAlgorithmCriterion $criterion;

		public function __construct(private string $value, private float $multiplier, private ?PriorityAlgorithmObject $valueobj = null) {}

		public function SetCriterion(PriorityAlgorithmCriterion $criterion): void { $this->criterion = $criterion; }
		public function GetCriterion(): PriorityAlgorithmCriterion { return $this->criterion; }

		public function GetValue(): string { return $this->value; }
		public function GetMultiplier(): float { return $this->multiplier; }
		public function GetValueObject(): ?PriorityAlgorithmObject { return $this->valueobj; }
	}

	final class PriorityAlgorithmObject
	{
		private array $procdata = array();
		private array $prioritydata = array();
		private float $priority = 1;

		public function __construct(private PriorityAlgorithm $algorithm, private mixed $object) {}

		public function GetObject(): mixed { return $this->object; }
		public function GetAlgorithm(): PriorityAlgorithm { return $this->algorithm; }
		
		public function GetPriorityData(): array { return $this->prioritydata; }
		public function GetPriority(): float { return $this->priority; }

		public function SetProcValue(string $key, mixed $value): void { $this->procdata[$key] = $value; }
		public function &GetProcValue(string $key): mixed { return $this->procdata[$key]; }

		public function UpdatePriority(): void
		{
			$this->priority = 1000;
			$this->prioritydata = array();

			foreach ($this->algorithm->GetCriterions() as $criterion)
			{
				if (!$criterion->ShouldApply($this, $this->GetAlgorithm())) continue;

				$data = $criterion->ApplyCriterion($this, $this->GetAlgorithm());
				if (!isset($data))
				{
					$this->priority = -1;
					break;
				}

				if ($data === true)
					$data = new PriorityAlgorithmCriterionData("+", 1);
				else if ($data === false)
					$data = new PriorityAlgorithmCriterionData("-", 0);

				$data->SetCriterion($criterion);

				$multiplier = $data->GetMultiplier();
				if ($multiplier != 1 && $multiplier > 0 && ($range = $criterion->GetModifierRange()) !== null)
				{
					$modifier = max($range[0], min($range[1], $criterion->GetModifier()));

					if ($multiplier > 1) $multiplier = 1 + ($multiplier - 1) * $modifier;
					else $multiplier **= $modifier;
				}

				$this->priority *= $multiplier;
				$this->prioritydata[] = $data;

				if ($this->priority <= 0) break;
			}

			/* usort($this->prioritydata, function($a, $b) { return abs($b->GetMultiplier() - 1) <=> abs($a->GetMultiplier() - 1); }); */
		}

		public function GetPriorityInfo(): array
		{
			$info = array(
				"priority" => number_format($this->priority, 3, ".", ""),
				"criterions" => array(),
			);

			foreach ($this->prioritydata as $num => $data)
			{
				$inf = $data->GetCriterion()->GetName().": ".$data->GetValue()." (x".number_format($data->GetMultiplier(), 3, ".", "").")";
				if ($data->GetValueObject() !== null) $inf .= ": ";

				$info["criterions"][] = ($num + 1).") ".$inf;

				if (($valueobj = $data->GetValueObject()) !== null)
				{
					$pinfo = $valueobj->GetPriorityInfo();
					$info["criterions"][] = "  Общий приоритет: ".$pinfo["priority"];

					foreach ($pinfo["criterions"] as $criterion)
						$info["criterions"][] = "  ".$criterion;
				}
			}

			return $info;
		}
	}

	final class PriorityAlgorithm
	{
		private array $objects = array();
		private array $procdata = array();

		public function __construct(private array $criterions) {}

		public function GetCriterions(): array { return $this->criterions; }
		public function GetObjects(): array { return $this->objects; }

		public function SetProcValue(string $key, mixed $value): void { $this->procdata[$key] = $value; }
		public function &GetProcValue(string $key): mixed { return $this->procdata[$key]; }

		public function AddObjectToProcess(mixed $object): PriorityAlgorithmObject
		{ return $this->objects[] = new PriorityAlgorithmObject($this, $object); }

		public function PrepareProcess(): void
		{
			$this->objects = array();
			$this->procdata = array();
		}

		public function Process(callable $callback, ?callable $fallback = null): bool
		{
			while (count($this->objects) > 0)
			{
				$prioritized = null;
				$prioritizedid = null;
				$priority = null;
				$skip = array();

				foreach ($this->objects as $id => $object)
				{
					$object->UpdatePriority();
					$prior = $object->GetPriority();

					if ($prior <= -1)
						$skip[] = $id;
					else if ($prior <= 0)
						continue;
					else if (!isset($priority) || $prior > $priority)
					{
						$prioritized = $object;
						$prioritizedid = $id;
						$priority = $prior;
					}
				}

				foreach ($skip as $id)
					unset($this->objects[$id]);

				if (isset($prioritized))
				{
					$result = $callback($prioritized) ?? null;

					if ($result === null)
						unset($this->objects[$prioritizedid]);
					else if ($result === true)
						break;
				}
				else if (isset($fallback) && $fallback())
					continue;
				else
					return false;
			}

			return true;
		}
	}
?>