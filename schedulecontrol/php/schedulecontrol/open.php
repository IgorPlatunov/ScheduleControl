<?php
	require_once("load.php");

	use ScheduleControl\{Core as Core, Config, UserConfig};

	$info = Config::GetParameter("DatabaseInfo", "Connection");
	Core\DataBase::Open(
		Config::GetParameter("DatabaseInfo", "Connection", "Host"),
		Config::GetParameter("DatabaseInfo", "Connection", "User"),
		Config::GetParameter("DatabaseInfo", "Connection", "Pass"),
		Config::GetParameter("DatabaseInfo", "Connection", "Port"),
	);

	$name = Config::GetParameter("DatabaseInfo", "Name");
	if (!Core\DataBase::IsDBExists($name))
		DataBaseUpdater::Update();

	Core\DataBase::SelectDB($name);
	UserConfig::Initialize();
?>