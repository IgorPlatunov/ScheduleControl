class StatisticGroups
{
	static elements = Shared.MapElements({
		date: "#group-date",
		group: "#group-container",
		general: "#general-info",
		subjects: "#subjects-info",
		update: "#update-btn",
		export: "#export-btn",
		collapse: "#general-info-collapse",
	});

	static sortbtns = Shared.MapVarElements(".subject-sort-btn", "id");
	static filters = Shared.MapVarElements(".subject-filter", "id");
	static columns = [];
	static rows = [];
	static sumrow = null;
	static sort = [];

	static group = null;
	static lastmsg = null;

	static validateDate()
	{
		if (!StatisticGroups.elements.date.reportValidity())
			return false;

		if (StatisticGroups.elements.date.value == "")
		{
			Shared.DisplayMessage("Некорректный формат даты", "error");
			return false;
		}

		return Shared.DateToSql(new Date(StatisticGroups.elements.date.value), false);
	}

	static SetupInfo(info)
	{
		StatisticGroups.elements.general.tBodies[0].innerHTML = "";
		StatisticGroups.elements.subjects.tBodies[0].innerHTML = "";
		StatisticGroups.rows.splice(0);
		
		if (info)
		{
			for (let [name, value] of Object.entries(info.general))
			{
				let row = document.createElement("tr");
				StatisticGroups.elements.general.tBodies[0].appendChild(row);

				let nametd = document.createElement("td");
				nametd.innerText = name;
				row.appendChild(nametd);

				let valuetd = document.createElement("td");
				valuetd.innerText = value;
				row.appendChild(valuetd);
			}

			for (let subject of info.subjects)
			{
				let row = document.createElement("tr");
				StatisticGroups.elements.subjects.tBodies[0].appendChild(row);
				StatisticGroups.rows.push(row);

				StatisticGroups.sumrow = row;

				for (let column of StatisticGroups.columns)
				{
					let cell = document.createElement("td");
					cell.Value = subject[column.sort.dataset.value];
					cell.innerText = column.sort.dataset.format.replace("%s", cell.Value);
					cell.Filters = [];
					row.appendChild(cell);

					if (column.sort.dataset.filter)
						for (let filter of column.sort.dataset.filter.split(";"))
							cell.Filters.push(subject[filter]);
				}
			}

			StatisticGroups.UpdateSubjectsSort();
			StatisticGroups.UpdateSubjectsFilter();
		}
	}

	static OpenInfo()
	{
		let group = StatisticGroups.group.Value;
		let name = StatisticGroups.group.Name;

		StatisticGroups.lastmsg?.remove();
		let info = Shared.DisplayMessage(`Загружается статистика группы ${name}...`, "warning");

		Shared.RequestGET("groupstatistic", {
			date: StatisticGroups.validateDate(),
			actdate: Header.GetActualDate(),
			group: group,
		}, function(data) {
			StatisticGroups.SetupInfo(data);

			StatisticGroups.lastmsg?.remove();
			StatisticGroups.lastmsg = Shared.DisplayMessage(`Загружена статистика группы ${name}`, "success");
		}, function (status, code, message) {
			StatisticGroups.lastmsg?.remove();
			StatisticGroups.lastmsg = Shared.DisplayMessage(`Не удалось загрузить статистику группы ${name} (${message})`, "error");
		}, function (status, message) {
			StatisticGroups.lastmsg?.remove();
			StatisticGroups.lastmsg = Shared.DisplayMessage(`Не удалось загрузить статистику группы ${name} (Ошибка HTTP ${status}: ${message})`, "error");
		}, function (status, message) {
			info.remove();
		});
	}

	static UpdateSubjectsSort()
	{
		StatisticGroups.rows.sort(function(a, b) {
			if (a == StatisticGroups.sumrow || b == StatisticGroups.sumrow)
				return a == StatisticGroups.sumrow ? 1 : -1;

			for (let column of StatisticGroups.sort)
			{
				let aval = a.cells[column].Value;
				let bval = b.cells[column].Value;

				if (aval == bval) continue;

				let iaval = parseFloat(aval);
				let ibval = parseFloat(bval);

				if (!isNaN(iaval) && !isNaN(ibval))
					return (StatisticGroups.sortbtns[column].Asc ? iaval < ibval : iaval > ibval) ? -1 : 1;
				else
					return (StatisticGroups.sortbtns[column].Asc ? aval < bval : aval > bval) ? -1 : 1;
			}

			return 0;
		});

		for (let row of StatisticGroups.rows)
			StatisticGroups.elements.subjects.tBodies[0].appendChild(row);
	}

	static UpdateSubjectsFilter()
	{
		let filters = {};

		for (let [id, column] of StatisticGroups.columns.entries())
			if (column.filter)
				filters[id] = column.filter.value.toLowerCase();

		for (let row of StatisticGroups.rows)
		{
			row.hidden = false;

			for (let [id, value] of Object.entries(filters))
			{
				if (value === "") continue;

				let found = false;

				for (let filter of row.cells[id].Filters)
					if (filter.toLowerCase().indexOf(value) >= 0)
						{ found = true; break; }

				if (!found) { row.hidden = true; break; }
			}
		}
	}

	static ExportStatistic()
	{
		let dialog = Shared.CreateDialog("Предпросмотр и экспорт статистики");
		let group = StatisticGroups.group.FullName;

		let body = document.createElement("iframe");
		body.className = "modaldialog-export-frame";
		dialog.Body.appendChild(body);
		
		body.addEventListener("load", function(event) {
			let doc = body.contentDocument;
			doc.body.innerHTML = `<style>
				table, tr, th { vertical-align: middle; text-align: center; font-family: "Times New Roman"; }
				td { height: 18px; }
			</style>`;

			let flex = doc.createElement("div");
			flex.style.display = "flex";
			flex.style.flexDirection = "column";
			flex.style.alignItems = "center";
			flex.style.rowGap = "20px";
			doc.body.appendChild(flex);

			let tab = doc.createElement("table");
			tab.style.borderCollapse = "collapse";
			tab.style.verticalAlign = "middle";
			tab.style.textAlign = "center";
			tab.style.fontFamily = "Times New Roman";

			let exportbtn = doc.createElement("button");
			exportbtn.innerText = "Сохранить в Excel";
			exportbtn.addEventListener("click", function(event) {
				Shared.ExportTableToExcel(tab, "Статистика группы", `Статистика группы ${group}`);
			});

			flex.appendChild(exportbtn);
			flex.appendChild(tab);

			if (body.Subjects)
			{
				let headtr = doc.createElement("tr");
				tab.appendChild(headtr);

				let headsubj = doc.createElement("th");
				headsubj.innerText = "Предмет";
				headsubj.style.border = "2px solid black";
				headsubj.style.fontWeight = "900";
				headtr.appendChild(headsubj);

				let headhours = doc.createElement("th");
				headhours.innerText = "Всего часов";
				headhours.style.borderTop = headhours.style.borderRight = headhours.style.borderBottom = "2px solid black";
				headhours.style.fontWeight = "900";
				headtr.appendChild(headhours);

				let headpassed = doc.createElement("th");
				headpassed.innerText = "Часов вычитано";
				headpassed.style.borderTop = headpassed.style.borderRight = headpassed.style.borderBottom = "2px solid black";
				headpassed.style.fontWeight = "900";
				headtr.appendChild(headpassed);

				let headleft = doc.createElement("th");
				headleft.innerText = "Часов осталось";
				headleft.style.borderTop = headleft.style.borderRight = headleft.style.borderBottom = "2px solid black";
				headleft.style.fontWeight = "900";
				headtr.appendChild(headleft);

				let headpercent = doc.createElement("th");
				headpercent.innerText = "Процент вычитки";
				headpercent.style.borderTop = headpercent.style.borderRight = headpercent.style.borderBottom = "2px solid black";
				headpercent.style.fontWeight = "900";
				headtr.appendChild(headpercent);

				let headhpday = doc.createElement("th");
				headhpday.innerText = "Часов в день";
				headhpday.style.borderTop = headhpday.style.borderRight = headhpday.style.borderBottom = "2px solid black";
				headhpday.style.fontWeight = "900";
				headtr.appendChild(headhpday);

				let rows = StatisticGroups.elements.subjects.tBodies[0].rows;
				for (let id in rows)
				{
					let i = parseInt(id);
					if (isNaN(i)) continue;

					let row = rows[i];

					let tr = doc.createElement("tr");
					tab.appendChild(tr);

					let subj = doc.createElement("td");
					subj.innerText = row.cells[0].innerText;
					subj.style.borderLeft = "2px solid black";
					subj.style.borderRight = "1px solid black";
					subj.style.borderBottom = i == rows.length - 1 ? "2px solid black" : "1px solid black";
					subj.style.textAlign = "left";
					tr.appendChild(subj);

					let hours = doc.createElement("td");
					hours.innerText = row.cells[1].innerText;
					hours.style.borderRight = "1px solid black";
					hours.style.borderBottom = i == rows.length - 1 ? "2px solid black" : "1px solid black";
					hours.style.textAlign = "right";
					tr.appendChild(hours);

					let passed = doc.createElement("td");
					passed.innerText = row.cells[2].innerText;
					passed.style.borderRight = "1px solid black";
					passed.style.borderBottom = i == rows.length - 1 ? "2px solid black" : "1px solid black";
					passed.style.textAlign = "right";
					tr.appendChild(passed);

					let left = doc.createElement("td");
					left.innerText = row.cells[3].innerText;
					left.style.borderRight = "1px solid black";
					left.style.borderBottom = i == rows.length - 1 ? "2px solid black" : "1px solid black";
					left.style.textAlign = "right";
					tr.appendChild(left);

					let percent = doc.createElement("td");
					percent.innerText = row.cells[4].innerText;
					percent.style.borderRight = "1px solid black";
					percent.style.borderBottom = i == rows.length - 1 ? "2px solid black" : "1px solid black";
					percent.style.textAlign = "right";
					tr.appendChild(percent);

					let hpday = doc.createElement("td");
					hpday.innerText = row.cells[5].innerText;
					hpday.style.borderRight = "2px solid black";
					hpday.style.borderBottom = i == rows.length - 1 ? "2px solid black" : "1px solid black";
					hpday.style.textAlign = "right";
					tr.appendChild(hpday);
				}
			}
			else
			{
				let headtr = doc.createElement("tr");
				tab.appendChild(headtr);

				let header = doc.createElement("th");
				header.innerText = `Общая информация по группе ${group}`;
				header.colSpan = 2;
				header.style.border = "2px solid black";
				header.style.fontWeight = "900";
				headtr.appendChild(header);

				let rows = StatisticGroups.elements.general.tBodies[0].rows;
				for (let id in rows)
				{
					let i = parseInt(id);
					if (isNaN(i)) continue;

					let row = rows[i];

					let tr = doc.createElement("tr");
					tab.appendChild(tr);

					let name = doc.createElement("td");
					name.innerText = row.cells[0].innerText;
					name.style.textAlign = "left";
					name.style.borderLeft = "2px solid black";
					name.style.borderRight = "1px solid black";
					name.style.borderBottom = i == rows.length - 1 ? "2px solid black" : "1px solid black";
					tr.appendChild(name);

					let value = doc.createElement("td");
					value.innerText = row.cells[1].innerText;
					value.style.textAlign = "right";
					value.style.borderLeft = "1px solid black";
					value.style.borderRight = "2px solid black";
					value.style.borderBottom = i == rows.length - 1 ? "2px solid black" : "1px solid black";
					tr.appendChild(value);
				}
			}
		});

		let subjects = dialog.AddOption("Общая информация", function(event) { body.Subjects = false; body.src = "about:blank"; });
		dialog.AddOption("Вычитка предметов", function(event) { body.Subjects = true; body.src = "about:blank"; });
		
		dialog.Center();

		subjects.click();
	}

	static Initialize()
	{
		for (let [id, sort] of Object.entries(StatisticGroups.sortbtns))
		{
			let filter = StatisticGroups.filters[id];

			StatisticGroups.columns[parseInt(id)] = {sort: sort, filter: filter};

			sort.addEventListener("click", function(event) {
				sort.Asc = !sort.Asc;
				sort.innerText = sort.Asc ? "▼" : "▲";

				Shared.RemoveFromArray(StatisticGroups.sort, id);
				StatisticGroups.sort.unshift(id);

				StatisticGroups.UpdateSubjectsSort();
			});

			filter?.addEventListener("input", function(event) {
				StatisticGroups.UpdateSubjectsFilter();
			});
		}

		StatisticGroups.group = Shared.ForeignKeyElement("Groups", "ID", null, [2, function() {
			return {date: StatisticGroups.validateDate()};
		}]);
		StatisticGroups.group.SetRequired(true);
		StatisticGroups.group.id = "group";
		StatisticGroups.elements.group.appendChild(StatisticGroups.group);

		StatisticGroups.elements.date.addEventListener("change", function(event) {
			let date = StatisticGroups.validateDate();
			if (date) Shared.SetCookie("statisticgroups-date", date);
		});

		StatisticGroups.elements.update.addEventListener("click", function(event) {
			StatisticGroups.SetupInfo();
			
			if (StatisticGroups.group.reportValidity() && StatisticGroups.validateDate())
				StatisticGroups.OpenInfo();
		});

		StatisticGroups.elements.export.addEventListener("click", function(event) {
			if (!StatisticGroups.group.reportValidity()) return;

			StatisticGroups.ExportStatistic();
		});

		StatisticGroups.elements.collapse.addEventListener("click", function(event) {
			let collapse = !StatisticGroups.elements.general.classList.contains("general-info-collapsed");
			StatisticGroups.elements.collapse.innerText = collapse ? "▲" : "▼";
			
			if (collapse) StatisticGroups.elements.general.classList.add("general-info-collapsed");
			else StatisticGroups.elements.general.classList.remove("general-info-collapsed");
		});
		StatisticGroups.elements.collapse.click();

		if (Shared.GetCookie("statisticgroups-date"))
			StatisticGroups.elements.date.value = Shared.GetCookie("statisticgroups-date");
	}
}
StatisticGroups.Initialize();