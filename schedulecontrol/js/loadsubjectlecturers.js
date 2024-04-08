class LoadSubjectLecturers
{
	static elements = Shared.MapElements({
		open: "#open-btn",
		save: "#save-btn",
		loads: "#loads",
		table: "#load-data-table",
		filter: "#load-filter",
	});

	static saved = true;
	static CurLoadIndex = null;
	static Loads = [];
	static LoadedYear = null;
	static lastmsg = null;

	static OpenYearLoad(year)
	{
		LoadSubjectLecturers.lastmsg?.remove();
		let info = Shared.DisplayMessage(`Загружается закрепления из нагрузки групп на ${year} год...`, "warning");

		Shared.RequestGET("yearloadsubjectlecturers", {
			year: year,
			actdate: Header.GetActualDate(),
		}, function(data) {
			LoadSubjectLecturers.LoadYearLoad(data);
			LoadSubjectLecturers.saved = true;
			LoadSubjectLecturers.LoadedYear = year;

			Shared.SetCookie("loadsubjectlecturers-year", year);

			LoadSubjectLecturers.lastmsg?.remove();
			LoadSubjectLecturers.lastmsg = Shared.DisplayMessage(`Загружены закрепления из нагрузки групп на ${year} год`, "success");
		}, function (status, code, message) {
			LoadSubjectLecturers.lastmsg?.remove();
			LoadSubjectLecturers.lastmsg = Shared.DisplayMessage(`Не удалось загрузить закрепления из нагрузки групп на ${year} год (${message})`, "error");
		}, function (status, message) {
			LoadSubjectLecturers.lastmsg?.remove();
			LoadSubjectLecturers.lastmsg = Shared.DisplayMessage(`Не удалось загрузить закрепления из нагрузки групп на ${year} год (Ошибка HTTP ${status}: ${message})`, "error");
		}, function (status, message) {
			info.remove();
		});
	}

	static LoadYearLoad(yearload)
	{
		LoadSubjectLecturers.Loads.splice(0);
		LoadSubjectLecturers.CurLoadIndex = null;
		LoadSubjectLecturers.elements.loads.innerHTML = "";

		for (let [id, load] of yearload.entries())
		{
			let option = document.createElement("option");
			option.innerText = load.load.name;
			option.value = id;
			
			LoadSubjectLecturers.elements.loads.appendChild(option);
			LoadSubjectLecturers.Loads.push(load.subjects);
		}

		LoadSubjectLecturers.SwitchGroupLoad(0, true);
	}

	static SwitchGroupLoad(index)
	{
		if (LoadSubjectLecturers.CurLoadIndex != null)
			LoadSubjectLecturers.Loads[LoadSubjectLecturers.CurLoadIndex] = LoadSubjectLecturers.ExportGroupLoad();

		if (LoadSubjectLecturers.Loads[index])
		{
			LoadSubjectLecturers.ImportGroupLoad(LoadSubjectLecturers.Loads[index]);
			LoadSubjectLecturers.CurLoadIndex = index;
		}
	}

	static ImportGroupLoad(load)
	{
		let table = LoadSubjectLecturers.elements.table;
		
		for (let element of LoadSubjectLecturers.elements.table.Elements)
			element.remove();

		LoadSubjectLecturers.elements.table.Elements.splice(0);

		let subjects = [];

		for (let subject of load)
		{
			let fsubj = null;

			for (let subj of subjects)
				if (subject.id.name == subj.Name && subject.id.fullname == subj.FullName)
				{ fsubj = subj; break; }

			if (!fsubj)
			{
				fsubj = {Name: subject.id.name, FullName: subject.id.fullname, Semesters: []};
				subjects.push(fsubj);
			}

			fsubj.Semesters.push(subject);
		}

		for (let subject of subjects)
		{
			let row = document.createElement("tr");
			table.tBodies[0].appendChild(row);
			table.Elements.push(row);

			let name = document.createElement("td");
			name.innerText = subject.FullName;
			row.appendChild(name);

			row.Name = subject.Name;
			row.FullName = subject.FullName;

			for (let semester = 0; semester < 2; semester++)
			{
				let cell = document.createElement("td");
				cell.colSpan = 2;
				row.appendChild(cell);
			}

			row.Semesters = {};

			for (let semester of subject.Semesters)
			{
				let sdata = {
					LoadSubjectID: semester.id,
					DeletedLecturers: semester.DeletedLecturers ?? [],
				};
				row.Semesters[semester.semester] = sdata;

				let lecturers = row.cells[1 + (semester.semester - 1)];
				let lecttable = document.createElement("table");
				lecttable.createTBody();
				lecttable.className = "subject-lecturers-table";
				lecturers.appendChild(lecttable);

				sdata.Lecturers = lecttable;

				lecttable.NewItem = function()
				{
					return Shared.ForeignKeyElement("Lecturers", "ID");
				}

				Shared.MakeTableAsItemList(lecttable, 2, 0, function() {
					let item = lecttable.NewItem();
					item.click();
					
					return item;
				}, function(row, grid, item) {
					item.SetRequired(true);

					let remove = grid.Remove;
					grid.Remove = function()
					{
						if (row.SubjectLecturerID != null)
						{
							if (!confirm("Данное закрепление сохранено для учета в расписании. При удалении вся информация, связанная с учетом закрепления преподавателя за этим предметом (например, количество отработанных часов), будет потеряна. Вы уверены?"))
								return;

							sdata.DeletedLecturers.push(item.SubjectLecturerID);
						}

						remove();
					}

					row.cells[1].className = "subject-lecturers-main-cell";

					row.MainBox = document.createElement("input");
					row.MainBox.type = "checkbox";
					row.MainBox.checked = item.IsMain ?? lecttable.Items.length == 1;
					row.cells[1].appendChild(row.MainBox);

					row.MainBox.addEventListener("change", function(event) { item.Changed = true; });
					item.addEventListener("change", function(event) { item.Changed = true; });

					row.SubjectLecturerID = item.SubjectLecturerID ?? null;

					item.addEventListener("fkeyselect", function(event) {
						if (event.detail.row) return;

						grid.Remove();
						event.preventDefault();
					})
				});

				let copy = document.createElement("button");
				copy.className = "subject-lecturers-copy";
				copy.innerText = "=";
				copy.title = "Установить преподавателей из этого семестра на весь курс";
				copy.addEventListener("click", function(event) {
					let confirmed = false;

					for (let [s, data] of Object.entries(row.Semesters))
						if (s != semester.semester)
							if (!confirmed)
								for (let item of data.Lecturers.Items)
									if (item.Row.SubjectLecturerID != null)
										if (!confirm("В другом семестре есть закрепления, сохраненные для учета в расписании. При удалении вся информация, связанная с учетом закреплений преподавателей за этим предметом (например, количество отработанных часов), будет потеряна. Вы уверены?"))
											return;
										else
											{ confirmed = true; break; }
					
					for (let [s, data] of Object.entries(row.Semesters))
						if (s != semester.semester)
						{
							data.Lecturers.ClearItems();

							for (let lecturer of lecttable.Items)
							{
								let item = data.Lecturers.NewItem();
								item.SetValue({id: lecturer.Item.Value, name: lecturer.Item.Name, fullname: lecturer.Item.FullName});
								item.IsMain = lecturer.Row.MainBox.checked;
								item.Changed = true;
			
								data.Lecturers.AddItem(item);
							}
						}
				});
				lecttable.tBodies[0].rows[0].cells[1].appendChild(copy);

				for (let lecturer of semester.lecturers)
				{
					let item = lecttable.NewItem();
					item.SetValue(lecturer.lecturer);
					item.SubjectLecturerID = lecturer.id;
					item.IsMain = lecturer.main == 1;
					item.Changed = lecturer.Changed ?? false;

					lecttable.AddItem(item);
				}
			}
		}
	}

	static ExportGroupLoad()
	{
		let table = LoadSubjectLecturers.elements.table;
		let data = [];

		for (let element of table.Elements)
			for (let [semester, sdata] of Object.entries(element.Semesters))
			{
				let subject = {
					id: sdata.LoadSubjectID,
					semester: semester,
					lecturers: [],
					DeletedLecturers: sdata.DeletedLecturers,
				};

				for (let lecturer of sdata.Lecturers.Items)
					if (lecturer.Item.Value)
						subject.lecturers.push({
							id: lecturer.Row.SubjectLecturerID,
							lecturer: {id: lecturer.Item.Value, name: lecturer.Item.Name, fullname: lecturer.Item.FullName},
							subject: sdata.LoadSubjectID,
							main: lecturer.Row.MainBox.checked ? 1 : 0,	
							Changed: lecturer.Item.Changed,
						});

				data.push(subject);
			}

		return data;
	}

	static UpdateFilter()
	{
		let value = LoadSubjectLecturers.elements.filter.value.toLowerCase();

		for (let elem of LoadSubjectLecturers.elements.table.Elements)
			elem.hidden =
				value != "" &&
				elem.FullName.toLowerCase().indexOf(value) < 0 &&
				elem.Name.toLowerCase().indexOf(value) < 0;
	}

	static Initialize()
	{
		LoadSubjectLecturers.elements.table.Elements = [];

		LoadSubjectLecturers.elements.table.addEventListener("change", function(event) {
			LoadSubjectLecturers.saved = false;
		});

		LoadSubjectLecturers.elements.open.addEventListener("click", function(event) {
			if (!LoadSubjectLecturers.saved && !Shared.UnsavedConfirm()) return;

			Shared.QuerySelectList("Выберите нагрузку групп на год", "yearload", {actdate: Header.GetActualDate()}, function(value, item) {
				item.innerText = `Нагрузка групп на ${value} год`;
			}, function(value, item) {
				LoadSubjectLecturers.OpenYearLoad(value);
			});
		});

		LoadSubjectLecturers.elements.save?.addEventListener("click", function(event) {
			let year = LoadSubjectLecturers.LoadedYear;
			if (!year) return;

			if (LoadSubjectLecturers.CurLoadIndex != null)
				LoadSubjectLecturers.Loads[LoadSubjectLecturers.CurLoadIndex] = LoadSubjectLecturers.ExportGroupLoad();

			let changes = {delete: [], save: []};

			for (let load of LoadSubjectLecturers.Loads)
				for (let subject of load)
				{
					if (subject.DeletedLecturers)
						for (let lecturer of subject.DeletedLecturers)
							changes.delete.push({id: lecturer.id});

					for (let lecturer of subject.lecturers)
						if (lecturer.Changed)
							changes.save.push({
								id: lecturer.id,
								subject: lecturer.subject,
								lecturer: lecturer.lecturer,
								main: lecturer.main,
							});
				}
			
			if (changes.save.length == 0 && changes.delete.length == 0)
			{
				LoadSubjectLecturers.lastmsg?.remove();
				LoadSubjectLecturers.lastmsg = Shared.DisplayMessage(`Изменений для сохранения не найдено`, "error");

				return;
			}

			if (!confirm("Сохранить текущие закрепления преподавателей за предметами нагрузки?")) return;

			LoadSubjectLecturers.lastmsg?.remove();
			let info = Shared.DisplayMessage(`Сохраняются изменения в закреплениях преподавателей за предметами нагрузки...`, "warning");

			Shared.RequestPOST("yearloadsubjectlecturers", changes, {
				actdate: Header.GetActualDate(),
			}, function(data) {
				LoadSubjectLecturers.lastmsg?.remove();
				Shared.DisplayMessage(`Изменения в закреплениях успешно сохранены`, "success");

				LoadSubjectLecturers.OpenYearLoad(year);
			}, function (status, code, message) {
				LoadSubjectLecturers.lastmsg?.remove();
				LoadSubjectLecturers.lastmsg = Shared.DisplayMessage(`Не удалось сохранить изменения в закреплениях (${message})`, "error");
			}, function (status, message) {
				LoadSubjectLecturers.lastmsg?.remove();
				LoadSubjectLecturers.lastmsg = Shared.DisplayMessage(`Не удалось сохранить изменения в закреплениях (Ошибка HTTP ${status}: ${message})`, "error");
			}, function (status, message) {
				info.remove();
			});
		});

		LoadSubjectLecturers.elements.loads.addEventListener("click", function(event) {
			if (LoadSubjectLecturers.Loads[LoadSubjectLecturers.elements.loads.value])
				LoadSubjectLecturers.SwitchGroupLoad(LoadSubjectLecturers.elements.loads.value);
		});
		
		LoadSubjectLecturers.elements.filter.addEventListener("input", function(event) {
			LoadSubjectLecturers.UpdateFilter();
		});

		onbeforeunload = function() {
			if (!LoadSubjectLecturers.saved)
				return "Закрепления не сохранены";
		}

		if (Shared.GetCookie("loadsubjectlecturers-year"))
			LoadSubjectLecturers.OpenYearLoad(Shared.GetCookie("loadsubjectlecturers-year"));

		if (LoadSubjectLecturers.elements.save)
			window.addEventListener("keydown", function(event) {
				if (!event.ctrlKey || event.code != "KeyS") return;

				event.preventDefault();

				LoadSubjectLecturers.elements.save.click();
			});
	}
}
LoadSubjectLecturers.Initialize();