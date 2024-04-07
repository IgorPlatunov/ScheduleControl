class YearLoads
{
	static elements = Shared.MapElements({
		add: "#add-btn",
		remove: "#remove-btn",
		clear: "#clear-btn",
		open: "#open-btn",
		export: "#export-btn",
		update: "#update-btn",
		save: "#save-btn",
		delete: "#delete-btn",
		year: "#editor-info-year",
		loads: "#editor-info-loads",
		name: "#editor-info-name",
		groupstable: "#groups-table",
		loadtable: "#load-data-table",
		filter: "#load-filter",
	});

	static saved = true;
	static Loads = [];
	static CurLoadID = null;
	static CurLoadIndex = null;
	static lastmsg = null;

	static validateYear()
	{
		return YearLoads.validateNumberInput(YearLoads.elements.year, "Неверный формат года");
	}

	static validateName()
	{
		if (!YearLoads.elements.name.reportValidity())
			return false;

		if (YearLoads.elements.name.value == "")
		{
			Shared.DisplayMessage("Название нагрузки не может быть пустым", "error");
			return false;
		}

		return YearLoads.elements.name.value;
	}

	static validateNumberInput(input, error)
	{
		if (!input.reportValidity())
			return false;

		let value = parseInt(input.value);
		if (isNaN(value))
		{
			Shared.DisplayMessage(error ?? "Неверный формат числа", "error");
			return false;
		}

		return value;
	}

	static ImportGroupLoad(data)
	{
		YearLoads.elements.groupstable.ClearItems();
		YearLoads.elements.loadtable.ClearItems();

		YearLoads.CurLoadID = data?.id?.id;
		YearLoads.elements.name.value = data?.name ?? `Новая нагрузка`;

		if (!data) return;

		for (let group of data.groups)
		{
			let item = YearLoads.elements.groupstable.NewItem();
			item.SetValue(group.group);
			item.LoadGroupID = group.id?.id;

			YearLoads.elements.groupstable.AddItem(item);
		}

		let subjects = [];

		for (let subject of data.subjects)
		{
			let item = null;

			for (let subj of subjects)
				if (
					subj.SubjectName == subject.subjname &&
					subj.SubjectAbbr == subject.subjabbr &&
					subj.Value == subject.subject.id
				) { item = subj; }

			if (!item)
			{
				item = Shared.ForeignKeyElement("Subjects", "ID");
				item.SetValue(subject.subject);

				item.SubjectName = subject.subjname;
				item.SubjectAbbr = subject.subjabbr;
				item.OneHourPriority = subject.onehour == 1;
				item.Semesters = [];

				subjects.push(item);
			}

			item.Semesters[subject.semester - 1] = {
				LoadSubjectID: subject.id?.id,
				Hours: subject.hours,
				WHours: subject.whours,
				LPHours: subject.lphours,
				CDHours: subject.cdhours,
				CDPHours: subject.cdphours,
				EHours: subject.ehours,
			};
		}

		for (let subject of subjects)
			YearLoads.elements.loadtable.AddItem(subject);

		YearLoads.UpdateFilter();
	}

	static ExportGroupLoad()
	{
		let data = {
			id: YearLoads.CurLoadID ? {id: YearLoads.CurLoadID} : null,
			name: YearLoads.elements.name.value,
			groups: [],
			subjects: [],
		};

		for (let grid of YearLoads.elements.groupstable.Items)
			if (grid.Item.Value)
				data.groups.push({
					id: grid.Row.LoadGroupID ? {id: grid.Row.LoadGroupID} : null,
					group: {id: grid.Item.Value, name: grid.Item.Name},
				});

		for (let grid of YearLoads.elements.loadtable.Items)
			if (grid.Item.Value)
				for (let [semester, sdata] of grid.Row.Semesters.entries())
				{
					let subject = grid.Row.ExportSubject(semester);
					if (subject) data.subjects.push(subject);
				}

		return data;
	}

	static ValidateGroupLoad()
	{
		if (!YearLoads.validateName())
			return false;

		if (YearLoads.elements.name.value === "")
		{
			Shared.DisplayMessage("Нагрузка не имеет названия", "error");
			return false;
		}

		for (let grid of YearLoads.elements.loadtable.Items)
			if (grid.Item.Value)
				for (let [semester, sdata] of grid.Row.Semesters.entries())
					if (!grid.Row.ValidateSubject(semester))
					{
						Shared.DisplayMessage(`Предмет нагрузки ${grid.Row.SubjectAbbr.value} (семестр ${semester + 1}) имеет некорректные данные`, "error");
						return false;
					}

		return true;
	}

	static UpdateCurLoadInList()
	{
		if (YearLoads.CurLoadIndex == null) return;

		YearLoads.Loads[YearLoads.CurLoadIndex] = YearLoads.ExportGroupLoad();
		YearLoads.elements.loads.children[YearLoads.CurLoadIndex].innerText = YearLoads.elements.name.value;
	}

	static SwitchGroupLoad(index)
	{
		YearLoads.UpdateCurLoadInList();

		YearLoads.ImportGroupLoad(YearLoads.Loads[index]);
		YearLoads.CurLoadIndex = index;

		if (!YearLoads.Loads[index])
		{
			index = YearLoads.Loads.push(YearLoads.ExportGroupLoad()) - 1;

			let option = document.createElement("option");
			option.innerText = YearLoads.elements.name.value;

			YearLoads.elements.loads.appendChild(option);

			option.value = index;
			YearLoads.elements.loads.value = index;
			YearLoads.CurLoadIndex = index;

			return index;
		}
	}

	static LoadYearLoad(yearload)
	{
		YearLoads.Loads.splice(0);
		YearLoads.CurLoadIndex = null;
		YearLoads.elements.loads.innerHTML = "";

		YearLoads.elements.year.value = yearload.year;

		for (let [id, load] of yearload.loads.entries())
		{
			let option = document.createElement("option");
			option.innerText = load.name;
			option.value = id;
			
			YearLoads.elements.loads.appendChild(option);
			YearLoads.Loads.push(load);
		}

		YearLoads.SwitchGroupLoad(0);
	}

	static OpenYearLoad(year)
	{
		YearLoads.lastmsg?.remove();
		let info = Shared.DisplayMessage(`Загружается нагрузка групп на ${year} год...`, "warning");

		Shared.RequestGET("yearload", {
			year: year,
			actdate: Header.GetActualDate(),
			build: 0,
		}, function(data) {
			YearLoads.LoadYearLoad(data);
			YearLoads.saved = true;

			YearLoads.lastmsg?.remove();
			YearLoads.lastmsg = Shared.DisplayMessage(`Загружена нагрузка групп на ${data.year} год`, "success");
		}, function (status, code, message) {
			YearLoads.lastmsg?.remove();
			YearLoads.lastmsg = Shared.DisplayMessage(`Не удалось загрузить нагрузку групп на ${year} год (${message})`, "error");
		}, function (status, message) {
			YearLoads.lastmsg?.remove();
			YearLoads.lastmsg = Shared.DisplayMessage(`Не удалось загрузить нагрузку групп на ${year} год (Ошибка HTTP ${status}: ${message})`, "error");
		}, function (status, message) {
			info.remove();
		});
	}

	static UpdateFilter()
	{
		let value = YearLoads.elements.filter.value.toLowerCase();

		for (let item of YearLoads.elements.loadtable.Items)
			item.Row.hidden =
				value != "" &&
				item.Item.Name.toLowerCase().indexOf(value) < 0 &&
				item.Item.FullName.toLowerCase().indexOf(value) < 0 &&
				item.Row.SubjectName.value.toLowerCase().indexOf(value) < 0 &&
				item.Row.SubjectAbbr.value.toLowerCase().indexOf(value) < 0;
	}

	static ExportLoad(load)
	{
		let dialog = Shared.CreateDialog("Предпросмотр и экспорт нагрузки группы на год");

		let body = document.createElement("iframe");
		body.className = "modaldialog-export-frame";
		dialog.Body.appendChild(body);

		body.addEventListener("load", function(event) {
			let doc = body.contentDocument;

			if (!body.load)
			{
				doc.body.innerText = "Выберите нагрузку для экспорта";
				return;
			}

			doc.body.innerText = "Обработка конечной нагрузки группы...";

			Shared.RequestPOST("export", body.load, {
				type: "yearload",
				actdate: Header.GetActualDate(),
			}, function(data) {
				doc.body.innerHTML = `<style>table, tr, th { vertical-align: middle; text-align: center; font-family: "Times New Roman"; }</style>`;

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
					Shared.ExportTableToExcel(tab, "Нагрузка группы на год", `Нагрузка группы ${data.name}`);
				});

				flex.appendChild(exportbtn);
				flex.appendChild(tab);

				let lrow = doc.createElement("tr");
				tab.appendChild(lrow);

				let load = doc.createElement("th");
				load.innerText = `Нагрузка группы ${data.name}`;
				load.style.fontSize = "32px";
				load.style.fontWeight = "900";
				load.colSpan = 3 + data.semesters.length * 6;
				lrow.appendChild(load);

				let spacerow = doc.createElement("tr");
				tab.appendChild(spacerow);

				let spacetd = doc.createElement("td");
				spacetd.style.height = "20px";
				spacetd.colSpan = 3 + data.semesters.length * 6;
				spacerow.appendChild(spacetd);

				let hrow = doc.createElement("tr");
				tab.appendChild(hrow);

				let subjhead = doc.createElement("th");
				subjhead.innerText = "Предмет";
				subjhead.style.fontWeight = "900";
				subjhead.style.border = "2px solid black";
				subjhead.rowSpan = 2;
				hrow.appendChild(subjhead);

				let lecthead = doc.createElement("th");
				lecthead.innerText = "Преподаватели";
				lecthead.style.fontWeight = "900";
				lecthead.style.borderTop = lecthead.style.borderRight = lecthead.style.borderBottom = "2px solid black";
				lecthead.rowSpan = 2;
				hrow.appendChild(lecthead);

				let hourhead = doc.createElement("th");
				hourhead.innerText = "Всего часов";
				hourhead.style.fontWeight = "900";
				hourhead.style.borderTop = hourhead.style.borderRight = hourhead.style.borderBottom = "2px solid black";
				hourhead.rowSpan = 2;
				hrow.appendChild(hourhead);

				let srow = doc.createElement("tr");
				tab.appendChild(srow);

				for (let semester of data.semesters)
				{
					let shead = doc.createElement("th");
					shead.innerText = "Семестр " + semester;
					shead.style.fontWeight = "900";
					shead.style.borderTop = shead.style.borderRight = shead.style.borderBottom = "2px solid black";
					shead.colSpan = 6;
					hrow.appendChild(shead);

					let hours = doc.createElement("th");
					hours.innerText = "Всего";
					hours.style.fontWeight = "900";
					hours.style.borderBottom = "2px solid black";
					hours.style.borderRight = "1px solid black";
					srow.appendChild(hours);

					let whours = doc.createElement("th");
					whours.innerText = "В неделю";
					whours.style.fontWeight = "900";
					whours.style.borderBottom = "2px solid black";
					whours.style.borderRight = "1px solid black";
					srow.appendChild(whours);

					let lphours = doc.createElement("th");
					lphours.innerText = "На ЛПЗ";
					lphours.style.fontWeight = "900";
					lphours.style.borderBottom = "2px solid black";
					lphours.style.borderRight = "1px solid black";
					srow.appendChild(lphours);

					let cdhours = doc.createElement("th");
					cdhours.innerText = "На КП";
					cdhours.style.fontWeight = "900";
					cdhours.style.borderBottom = "2px solid black";
					cdhours.style.borderRight = "1px solid black";
					srow.appendChild(cdhours);

					let cdphours = doc.createElement("th");
					cdphours.innerText = "На ЗКП";
					cdphours.style.fontWeight = "900";
					cdphours.style.borderBottom = "2px solid black";
					cdphours.style.borderRight = "1px solid black";
					srow.appendChild(cdphours);

					let ehours = doc.createElement("th");
					ehours.innerText = "На Экз";
					ehours.style.fontWeight = "900";
					ehours.style.borderBottom = ehours.style.borderRight = "2px solid black";
					srow.appendChild(ehours);
				}

				let lsrow = null;
				for (let subject of data.subjects)
				{
					let subjrow = lsrow = doc.createElement("tr");
					tab.appendChild(subjrow);

					let subjname = doc.createElement("td");
					subjname.innerText = subject.name;
					subjname.style.borderLeft = subjname.style.borderRight = "2px solid black";
					subjname.style.borderBottom = "1px solid black";
					subjname.style.textAlign = "left";
					subjrow.appendChild(subjname);

					let lectname = doc.createElement("td");
					lectname.innerText = subject.lecturers;
					lectname.style.borderRight = "2px solid black";
					lectname.style.borderBottom = "1px solid black";
					lectname.style.textAlign = "left";
					subjrow.appendChild(lectname);

					let fhours = doc.createElement("td");
					fhours.innerText = subject.hours;
					fhours.style.borderRight = "2px solid black";
					fhours.style.borderBottom = "1px solid black";
					fhours.style.textAlign = "right";
					subjrow.appendChild(fhours);

					for (let semester of data.semesters)
					{
						let sdata = subject.semesters[semester];

						let hours = doc.createElement("td");
						hours.innerText = sdata.hours;
						hours.style.borderRight = hours.style.borderBottom = "1px solid black";
						hours.style.textAlign = "right";
						subjrow.appendChild(hours);

						let whours = doc.createElement("td");
						whours.innerText = sdata.whours;
						whours.style.borderRight = whours.style.borderBottom = "1px solid black";
						whours.style.textAlign = "right";
						subjrow.appendChild(whours);

						let lphours = doc.createElement("td");
						lphours.innerText = sdata.lphours;
						lphours.style.borderRight = lphours.style.borderBottom = "1px solid black";
						lphours.style.textAlign = "right";
						subjrow.appendChild(lphours);

						let cdhours = doc.createElement("td");
						cdhours.innerText = sdata.cdhours;
						cdhours.style.borderRight = cdhours.style.borderBottom = "1px solid black";
						cdhours.style.textAlign = "right";
						subjrow.appendChild(cdhours);

						let cdphours = doc.createElement("td");
						cdphours.innerText = sdata.cdphours;
						cdphours.style.borderRight = cdphours.style.borderBottom = "1px solid black";
						cdphours.style.textAlign = "right";
						subjrow.appendChild(cdphours);

						let ehours = doc.createElement("td");
						ehours.innerText = sdata.ehours;
						ehours.style.borderRight = "2px solid black";
						ehours.style.borderBottom = "1px solid black";
						ehours.style.textAlign = "right";
						subjrow.appendChild(ehours);
					}
				}

				if (lsrow)
					for (let cell of lsrow.cells)
						cell.style.borderBottom = "2px solid black";
			}, function (status, code, message) {
				doc.body.innerText = `Не удалось обработать конечную нагрузку группы (${message})`;
			}, function (status, message) {
				doc.body.innerText = `Не удалось обработать конечную нагрузку группы (Ошибка HTTP ${status}: ${message})`;
			});
		});
		body.src = "about:blank";

		for (let gload of load.loads)
			dialog.AddOption(gload.name, function(event) { body.load = gload; body.src = "about:blank"; });
	
		dialog.Center();
	}

	static Initialize()
	{
		let groupstable = YearLoads.elements.groupstable;
		groupstable.NewItem = function()
		{
			return Shared.ForeignKeyElement("Groups", "ID", null, [0, function() {
				let current = [];
				for (let item of groupstable.Items)
					if (item.Item.Value) current.push(item.Item.Value);

				return current;
			}]);
		}

		Shared.MakeTableAsItemList(groupstable, 1, 0, function() {
			let item = groupstable.NewItem();
			item.click();

			return item;
		}, function(row, grid, item) {
			item.SetRequired(true);

			let remove = grid.Remove;
			grid.Remove = function()
			{
				if (row.LoadGroupID && !confirm("Данная группа сейчас сохранена в нагрузке. Удалить группу из нагрузки?"))
					return;

				remove();
			};

			row.LoadGroupID = item.LoadGroupID;

			item.addEventListener("fkeyselect", function(event) {
				if (event.detail.row) return;
				
				grid.Remove();
				event.preventDefault();
			});
		});

		let loadtable = YearLoads.elements.loadtable;

		Shared.MakeTableAsItemList(loadtable, 16, 0, function(additem) {
			return Shared.ForeignKeyElement("Subjects", "ID");
		}, function(row, grid, item) {
			item.SetRequired(true);

			let remove = grid.Remove;
			grid.Remove = function()
			{
				for (let semester of row.Semesters)
					if (semester.LoadSubjectID && !confirm("Данный предмет сохранен для учёта в расписании. При удалении вся информация, связанная с учётом этого предмета (например, количество вычетанных часов), будет потеряна. Вы уверены?"))
						return;

				remove();
			}

			row.SubjectName = document.createElement("input");
			row.SubjectName.type = "text";
			row.SubjectName.minlength = 1;
			if (item.SubjectName) row.SubjectName.value = item.SubjectName;
			row.cells[1].appendChild(row.SubjectName);

			row.SubjectAbbr = document.createElement("input");
			row.SubjectAbbr.type = "text";
			row.SubjectAbbr.minlength = 1;
			if (item.SubjectAbbr) row.SubjectAbbr.value = item.SubjectAbbr;
			row.cells[2].appendChild(row.SubjectAbbr);

			row.OneHourPriority = document.createElement("input");
			row.OneHourPriority.type = "checkbox";
			row.OneHourPriority.checked = item.OneHourPriority ?? false;
			row.cells[3].appendChild(row.OneHourPriority);

			row.Semesters = [];

			for (let semester = 0; semester < 2; semester++)
			{
				for (let cell = 4; cell <= 9; cell++)
					row.cells[cell + semester * 6].className = `load-semester${semester + 1}`;

				let hours = document.createElement("input");
				hours.type = "number";
				hours.min = 0;
				row.cells[4 + semester * 6].appendChild(hours);

				let whours = document.createElement("input");
				whours.type = "number";
				whours.min = 0;
				row.cells[5 + semester * 6].appendChild(whours);

				let lphours = document.createElement("input");
				lphours.type = "number";
				lphours.min = 0;
				row.cells[6 + semester * 6].appendChild(lphours);

				let cdhours = document.createElement("input");
				cdhours.type = "number";
				cdhours.min = 0;
				row.cells[7 + semester * 6].appendChild(cdhours);

				let cdphours = document.createElement("input");
				cdphours.type = "number";
				cdphours.min = 0;
				row.cells[8 + semester * 6].appendChild(cdphours);

				let ehours = document.createElement("input");
				ehours.type = "number";
				ehours.min = 0;
				row.cells[9 + semester * 6].appendChild(ehours);

				row.Semesters[semester] = {
					Hours: hours,
					WHours: whours,
					LPHours: lphours,
					CDHours: cdhours,
					CDPHours: cdphours,
					EHours: ehours,
				};

				if (item.Semesters && item.Semesters[semester])
				{
					row.Semesters[semester].LoadSubjectID = item.Semesters[semester].LoadSubjectID;

					hours.value = item.Semesters[semester].Hours;
					whours.value = item.Semesters[semester].WHours;
					lphours.value = item.Semesters[semester].LPHours;
					cdhours.value = item.Semesters[semester].CDHours;
					cdphours.value = item.Semesters[semester].CDPHours;
					ehours.value = item.Semesters[semester].EHours;
				}

				let lasthours = hours.value;
				hours.addEventListener("change", function(event) {
					if (row.Semesters[semester].LoadSubjectID && (hours.value == 0 || hours.value == ""))
						if (!confirm("Удаление общего количества часов удалит этот предмет из учёта на данном семестре. Вся информация, связанная с учётом этого предмета на этом семестре (например, количество вычетанных часов), будет потеряна. Вы уверены?"))
						{
							hours.value = lasthours;
							return;							
						}

					lasthours = hours.value;
				});
			}

			row.ValidateSubject = function(semester)
			{
				let sdata = row.Semesters[semester];
				if (sdata.Hours.value == "" || sdata.Hours.value == "0")
					return true;

				return row.SubjectName.reportValidity() !== false &&
					row.SubjectAbbr.reportValidity() !== false &&
					YearLoads.validateNumberInput(sdata.Hours, "Некорректное значение общего количества часов") !== false &&
					YearLoads.validateNumberInput(sdata.WHours, "Некорректное значение количества часов в неделю") !== false &&
					YearLoads.validateNumberInput(sdata.LPHours, "Некорректное значение количества часов на ЛПЗ") !== false &&
					YearLoads.validateNumberInput(sdata.CDHours, "Некорректное значение количества часов на КП") !== false &&
					YearLoads.validateNumberInput(sdata.CDPHours, "Некорректное значение количества часов на защиту КП") !== false &&
					YearLoads.validateNumberInput(sdata.EHours, "Некорректное значение количества часов на экзамен") !== false;
			}

			row.ExportSubject = function(semester)
			{
				let sdata = row.Semesters[semester];
				if (sdata.Hours.value == "" || sdata.Hours.value == "0")
					return null;

				return {
					id: sdata.LoadSubjectID ? {id: sdata.LoadSubjectID} : null,
					subject: {id: item.Value, name: item.Name},
					subjname: row.SubjectName.value,
					subjabbr: row.SubjectAbbr.value,
					semester: semester + 1,
					hours: sdata.Hours.value,
					whours: sdata.WHours.value,
					lphours: sdata.LPHours.value,
					cdhours: sdata.CDHours.value,
					cdphours: sdata.CDPHours.value,
					ehours: sdata.EHours.value,
					onehour: row.OneHourPriority.checked ? 1 : 0,
				}
			}

			item.addEventListener("fkeyselect", function(event) {
				if (!event.detail.row)
				{
					grid.Remove();
					event.preventDefault();
				}
				else
				{
					row.SubjectName.value = event.detail.row.Cells.Name.Input.GetDataValue();
					row.SubjectAbbr.value = event.detail.row.Cells.Abbreviation.Input.GetDataValue();
				}
			});
		});

		groupstable.addEventListener("change", function(event) {
			YearLoads.saved = false;
		});

		loadtable.addEventListener("change", function(event) {
			YearLoads.saved = false;
		});

		YearLoads.elements.year.addEventListener("change", function(event) {
			YearLoads.saved = false;

			let year = YearLoads.validateYear()
			if (year) Shared.SetCookie("editor-yearload-year", year);
		});

		YearLoads.elements.name.addEventListener("change", function(event) {
			YearLoads.saved = false;

			let name = YearLoads.validateName();
			if (name) YearLoads.elements.loads.children[YearLoads.CurLoadIndex].innerText = name;
		});

		YearLoads.elements.add?.addEventListener("click", function(event) {
			if (YearLoads.ValidateGroupLoad())
			{
				YearLoads.saved = false;
				YearLoads.SwitchGroupLoad();
			}
		});

		YearLoads.elements.remove?.addEventListener("click", function(event) {
			if (!confirm("Удалить выбранную нагрузку группы?")) return;

			YearLoads.saved = false;

			let index = YearLoads.CurLoadIndex;
			if (index != null)
			{
				for (let i = parseInt(index) + 1; i < YearLoads.Loads.length; i++)
					YearLoads.elements.loads.children[i].value--;

				YearLoads.elements.loads.children[index].remove();
				YearLoads.Loads.splice(index, 1);
			}

			index = index ? Math.max(0, parseInt(index) - 1) : 0;

			YearLoads.elements.loads.value = index;
			YearLoads.CurLoadIndex = null;
			YearLoads.SwitchGroupLoad(index);
		});

		YearLoads.elements.clear?.addEventListener("click", function(event) {
			if (!YearLoads.saved && !Shared.UnsavedConfirm()) return;

			if (!confirm("Удалить все нагрузки в редакторе? Это позволит создать новую нагрузку на год с нуля"))
				return;

			YearLoads.saved = true;
			YearLoads.Loads.splice(0);

			YearLoads.elements.loads.innerHTML = "";
			YearLoads.elements.loads.value = null;

			YearLoads.CurLoadIndex = null;
			YearLoads.SwitchGroupLoad();
		});

		YearLoads.elements.open.addEventListener("click", function(event) {
			if (!YearLoads.saved && !Shared.UnsavedConfirm()) return;

			Shared.QuerySelectList("Выберите нагрузку групп на год", "yearload", {actdate: Header.GetActualDate()}, function(value, item) {
				item.innerText = `Нагрузка групп на ${value} год`;
			}, function(value, item) {
				YearLoads.OpenYearLoad(value);
			});
		});

		YearLoads.elements.export.addEventListener("click", function(event) {
			if (YearLoads.CurLoadIndex != null && !YearLoads.ValidateGroupLoad()) return;
			
			YearLoads.UpdateCurLoadInList();

			let year = YearLoads.validateYear();
			if (!year) return;
	
			let data = {year: year, loads: []};

			for (let load of YearLoads.Loads)
				data.loads.push(load);

			YearLoads.ExportLoad(data);
		});

		YearLoads.elements.update?.addEventListener("click", function(event) {
			if (!YearLoads.saved && !Shared.UnsavedConfirm()) return;

			let year = YearLoads.validateYear();
			if (!year) return;

			if (!confirm(`Сформировать нагрузку по данным из учебных планов на ${year} год?`))
				return;

			YearLoads.lastmsg?.remove();
			let info = Shared.DisplayMessage(`Формируется нагрузка групп на ${year} год...`, "warning");
	
			Shared.RequestGET("yearload", {
				year: year,
				actdate: Header.GetActualDate(),
				build: 1,
			}, function(data) {
				YearLoads.LoadYearLoad(data);
	
				YearLoads.lastmsg?.remove();
				YearLoads.lastmsg = Shared.DisplayMessage(`Сформирована нагрузка групп на ${data.year} год`, "success");
			}, function (status, code, message) {
				YearLoads.lastmsg?.remove();
				YearLoads.lastmsg = Shared.DisplayMessage(`Не удалось сформировать нагрузку групп на ${year} год (${message})`, "error");
			}, function (status, message) {
				YearLoads.lastmsg?.remove();
				YearLoads.lastmsg = Shared.DisplayMessage(`Не удалось сформировать нагрузку групп на ${year} год (Ошибка HTTP ${status}: ${message})`, "error");
			}, function (status, message) {
				info.remove();
			});
		});

		YearLoads.elements.save?.addEventListener("click", function(event) {
			if (YearLoads.CurLoadIndex != null && !YearLoads.ValidateGroupLoad()) return;
			
			YearLoads.UpdateCurLoadInList();

			let year = YearLoads.validateYear();
			if (!year || !confirm(`Сохранить текущую нагрузку на ${year} год?`)) return;

			YearLoads.lastmsg?.remove();
			let info = Shared.DisplayMessage(`Сохраняется нагрузка групп на ${year} год...`, "warning");
	
			let data = {year: year, loads: []};

			for (let load of YearLoads.Loads)
				data.loads.push(load);

			Shared.RequestPOST("yearload", data, {
				actdate: Header.GetActualDate(),
				type: "save",
			}, function(data) {
				YearLoads.lastmsg?.remove();
				Shared.DisplayMessage(`Нагрузка групп на ${year} год успешно сохранена.`, "success");

				YearLoads.OpenYearLoad(year);
			}, function (status, code, message) {
				YearLoads.lastmsg?.remove();
				YearLoads.lastmsg = Shared.DisplayMessage(`Не удалось сохранить нагрузку групп на ${year} год (${message})`, "error");
			}, function (status, message) {
				YearLoads.lastmsg?.remove();
				YearLoads.lastmsg = Shared.DisplayMessage(`Не удалось сохранить нагрузку групп на ${year} год (Ошибка HTTP ${status}: ${message})`, "error");
			}, function (status, message) {
				info.remove();
			});
		});

		YearLoads.elements.delete?.addEventListener("click", function(event) {
			let year = YearLoads.validateYear();
			if (!year || !confirm(`Удалить нагрузку на ${year} год?`)) return;

			YearLoads.lastmsg?.remove();
			let info = Shared.DisplayMessage(`Удаляется нагрузка групп на ${year} год...`, "warning");

			Shared.RequestPOST("yearload", {}, {
				actdate: Header.GetActualDate(),
				type: "delete",
				year: year,
			}, function(data) {
				YearLoads.lastmsg?.remove();
				YearLoads.lastmsg = Shared.DisplayMessage(`Нагрузка групп на ${year} год успешно удалена.`, "success");
			}, function (status, code, message) {
				YearLoads.lastmsg?.remove();
				YearLoads.lastmsg = Shared.DisplayMessage(`Не удалось удалить нагрузку групп на ${year} год (${message})`, "error");
			}, function (status, message) {
				YearLoads.lastmsg?.remove();
				YearLoads.lastmsg = Shared.DisplayMessage(`Не удалось удалить нагрузку групп на ${year} год (Ошибка HTTP ${status}: ${message})`, "error");
			}, function (status, message) {
				info.remove();
			});
		});

		YearLoads.elements.loads.addEventListener("change", function(event) {
			if (YearLoads.Loads[YearLoads.elements.loads.value])
			{
				if (YearLoads.ValidateGroupLoad())
					YearLoads.SwitchGroupLoad(YearLoads.elements.loads.value);
				else
					YearLoads.elements.loads.value = YearLoads.CurLoadIndex;
			}
		});

		YearLoads.elements.filter.addEventListener("input", function(event) {
			YearLoads.UpdateFilter();
		})

		onbeforeunload = function() {
			if (!YearLoads.saved)
				return "Нагрузка не сохранена";
		}

		YearLoads.SwitchGroupLoad();

		if (Shared.GetCookie("editor-yearload-year"))
			YearLoads.elements.year.value = Shared.GetCookie("editor-yearload-year");

		let year = YearLoads.validateYear();
		if (year) YearLoads.OpenYearLoad(year);

		YearLoads.saved = true;

		if (YearLoads.elements.save)
			window.addEventListener("keydown", function(event) {
				if (!event.ctrlKey || event.code != "KeyS") return;

				event.preventDefault();

				YearLoads.elements.save.click();
			});
	}
}
YearLoads.Initialize();