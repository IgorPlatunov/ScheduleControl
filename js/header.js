class Header
{
	static elements = Shared.MapElements({
		actdate: "#header-actdate",
		actdateauto: "#header-actdate-auto",
		scale: "#header-scale",
		scaleinfo: "#header-scale-label",
	});

	static GetActualDate()
	{
		if (Header.elements.actdateauto.checked || Header.elements.actdate.value == "")
			return "now";
		
		return Shared.DateToSql(new Date(Header.elements.actdate.value), true);
	}

	static UpdateScaleUI(wndw)
	{
		if (!wndw)
		{
			Header.UpdateScaleUI(window);

			for (let f = 0; f < frames.length; f++)
				Header.UpdateScaleUI(frames[f]);
		}
		else
			wndw.document.documentElement.style.fontSize = `${16 * Header.GetUIScale()}px`;
	}

	static GetUIScale() { return Header.elements.scale.value / 100; }

	static Initialize()
	{
		if (Shared.GetCookie("actdateauto"))
			Header.elements.actdateauto.checked = Shared.GetCookie("actdateauto")=="1";
		else
			Header.elements.actdateauto.checked = true;

		if (Shared.GetCookie("actdateauto") != "0")
			Header.elements.actdate.disabled = true;

		if (Shared.GetCookie("actdate"))
			Header.elements.actdate.value = Shared.GetCookie("actdate");

		if (Shared.GetCookie("scaleui"))
		{
			Header.elements.scale.value = Shared.GetCookie("scaleui");
			Header.elements.scaleinfo.innerText = `${Header.elements.scale.value}%`;
			Header.UpdateScaleUI();
		}

		Header.elements.actdateauto.addEventListener("change", function(event) {
			Header.elements.actdate.disabled = Header.elements.actdateauto.checked;

			Shared.SetCookie("actdateauto", Header.elements.actdateauto.checked ? "1" : "0");
		});

		Header.elements.actdate.addEventListener("change", function(event) {
			Shared.SetCookie("actdate", Header.elements.actdate.value);
		});

		Header.elements.scale.addEventListener("input", function(event) {
			Header.elements.scaleinfo.innerText = `${Header.elements.scale.value}%`;
		});

		Header.elements.scale.addEventListener("change", function(event) {
			Header.UpdateScaleUI();

			Shared.SetCookie("scaleui", Header.elements.scale.value);
		});
	}
}
Header.Initialize();

window.GetActualDate = function() { return Header.GetActualDate(); }
window.UpdateScaleUI = function(window) { Header.UpdateScaleUI(window); }
window.GetUIScale = function() { return Header.GetUIScale(); }