<?php
	use ScheduleControl\{Core as Core};

	require_once("../php/ajaxrequests.php");

	AJAXRequest::Proccess(function() {
		$type = AJAXRequest::GetParameter("type");

		if ($type == "privileges")
		{
			$privs = array();

			foreach (Core\UserPrivileges::From(Core\UserRoles::GetPrivileges(AJAXRequest::GetParameter("role"))) as $privilege)
				$privs[$privilege] = Core\UserPrivileges::GetNiceName($privilege);

			AJAXRequest::Response($privs);
		}
		else if ($type == "all")
		{
			$roles = array();

			foreach(Core\UserRoles::GetRoles(true) as $role => $privileges)
			{
				$roledata = array("name" => Core\UserRoles::GetNiceName($role), "privileges" => array());

				foreach (Core\UserPrivileges::From($privileges) as $privilege)
					$roledata["privileges"][$privilege] = Core\UserPrivileges::GetNiceName($privilege);

				$roles[$role] = $roledata;
			}

			AJAXRequest::Response($roles);
		}
		else
			AJAXRequest::ClientError(0, "Неизвестный тип запроса");
	});
?>