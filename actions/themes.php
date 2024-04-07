<?php
	if (PageRequest::GetParameter("type") === "theme" && PageRequest::IsParameterSet("theme"))
		Themes::SetCurrentTheme(PageRequest::GetParameter("theme"));
?>