<?php
	use ScheduleControl\Core\{DBTableRegistryCollections as Collections, DBTableRegistryCollection as Collection};

	require_once("../php/ajaxrequests.php");

	function GetCollectionData(string $name): ?array
	{
		$collection = Collections::GetCollection($name);
		if (!isset($collection)) return null;

		return array(
			"name" => $name,
			"table" => $collection->GetTable()->GetInfo()->GetName(),
			"nicename" => $collection->GetTable()->GetInfo()->GetNiceName(),
			"access" => $collection->HasAccess(Session::CurrentUser()) ? 1 : 0,
			"internal" => $collection->IsInternal() ? 1 : 0,
		);
	}

	AJAXRequest::Proccess(function() {
		$type = AJAXRequest::GetParameter("type");

		if ($type == "all")
		{
			$collections = array();

			foreach (Collections::GetCollections() as $name => $collection)
				$collections[] = GetCollectionData($name);

			AJAXRequest::Response($collections);	
		}
		else if ($type == "name" || $type == "bytable")
		{
			$name = AJAXRequest::GetParameter("name");

			if ($type == "bytable")
				foreach (Collections::GetCollections() as $cname => $collection)
					if ($collection->GetTable()->GetInfo()->GetName() == $name)
					{ $name = $cname; break; }

			$collection = GetCollectionData($name);
			if (!isset($collection)) AJAXRequest::ClientError(1, "Коллекция регистров не найдена");

			AJAXRequest::Response($collection);
		}
		else
			AJAXRequest::ClientError(0, "Незивестный тип запроса");
	});
?>