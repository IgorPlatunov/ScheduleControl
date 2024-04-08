class SemesterSchedules
{
	static elements = Shared.MapElements({
		add: "#add-btn",
		clear: "#clear-btn",
		open: "#open-btn",
		export: "#export-btn",
		update: "#update-btn",
		configure: "#configure-btn",
		validate: "#validate-btn",
		save: "#save-btn",
		delete: "#delete-btn",
		editorinfo: "#editor-info",
		unalloc: "#unalloc-pairs-table",
		unallocclose: "#unalloc-pairs-table-close",
		start: "#editor-info-start",
		end: "#editor-info-end",
		wday: "#editor-info-wday",
		year: "#editor-info-year",
		semester: "#editor-info-semester",
		schedule: "#schedule-container",
	});

	static WDays = [];
	static WDayIndex = null;
	static Loads = [];
	static saved = true;
	static lastmsg = null;

	static validateYear()
	{
		if (!SemesterSchedules.elements.year.reportValidity())
			return false;

		let value = parseInt(SemesterSchedules.elements.year.value);
		if (isNaN(value))
		{
			Shared.DisplayMessage("Неверный формат года", "error");
			return false;
		}

		return value;
	}

	static getSemester()
	{ return parseInt(SemesterSchedules.elements.semester.value); }

	static AddLoad(load)
	{
		let special = {Load: load.load.name, Semester: SemesterSchedules.getSemester()};

		let table = document.createElement("table");
		SemesterSchedules.elements.schedule.appendChild(table);
		SemesterSchedules.Loads.push(table);

		let colgroup = document.createElement("colgroup");
		table.appendChild(colgroup);

		let colinfo = document.createElement("col");
		colinfo.className = "schedule-load-col-info";
		colinfo.span = 2;
		colgroup.appendChild(colinfo);

		let colsubj = document.createElement("col");
		colsubj.className = "schedule-load-col-subject";
		colgroup.appendChild(colsubj);

		let coltype = document.createElement("col");
		coltype.className = "schedule-load-col-type";
		colgroup.appendChild(coltype);

		table.Load = load.load;

		let thead = table.createTHead();

		let loadtr = document.createElement("tr");
		thead.appendChild(loadtr);

		let loadlockth = document.createElement("th");
		loadtr.appendChild(loadlockth);

		let loadlockgrid = document.createElement("div");
		loadlockgrid.className = "schedule-load-lock-grid";
		loadlockth.appendChild(loadlockgrid);

		let deleteload = document.createElement("button");
		deleteload.className = "schedule-load-delete";
		deleteload.innerText = "✕";
		deleteload.addEventListener("click", function(event) {
			if (!confirm("Удалить нагрузку группы из расписания?")) return;

			table.remove();
			Shared.RemoveFromArray(SemesterSchedules.Loads, table);
		});
		loadlockgrid.appendChild(deleteload);

		table.LoadLock = document.createElement("input");
		table.LoadLock.type = "checkbox";
		table.LoadLock.checked = load.Locked ?? false;
		table.LoadLock.title = "Не изменять расписание этой нагрузки при автоматическом формировании расписания";
		loadlockgrid.appendChild(table.LoadLock);

		table.LoadLock.addEventListener("change", function(event) {
			if (table.LoadLock.checked) table.classList.add("schedule-load-locked");
			else table.classList.remove("schedule-load-locked");

			for (let item of table.Items)
				item.Item.disabled = table.LoadLock.checked;
		});

		if (table.LoadLock.checked) table.classList.add("schedule-load-locked");

		let loadnameth = document.createElement("th");
		loadnameth.colSpan = 3;
		loadtr.appendChild(loadnameth);

		let loadnamegrid = document.createElement("div");
		loadnamegrid.className = "schedule-load-name-grid";
		loadnameth.appendChild(loadnamegrid);
		
		let loadname = document.createElement("span");
		loadname.innerText = load.load.name;
		loadnamegrid.appendChild(loadname);

		let loadcollapse = document.createElement("button");
		loadcollapse.innerText = "▼";
		loadcollapse.title = "Свернуть/развернуть";
		loadnamegrid.appendChild(loadcollapse);
		loadcollapse.addEventListener("click", function(event) {
			loadcollapse.Collapsed = !loadcollapse.Collapsed;
			loadcollapse.innerText = loadcollapse.Collapsed ? "▲" : "▼";

			if (loadcollapse.Collapsed) table.classList.add("schedule-load-collapsed");
			else table.classList.remove("schedule-load-collapsed");
		});

		let tabletr = document.createElement("tr");
		thead.appendChild(tabletr);

		let tablelock = document.createElement("th");
		tablelock.innerText = "Блок";
		tabletr.appendChild(tablelock);

		let tablepair = document.createElement("th");
		tablepair.innerText = "Номер пары";
		tabletr.appendChild(tablepair);

		let tablesubjects = document.createElement("th");
		tablesubjects.innerText = "Предметы";
		tabletr.appendChild(tablesubjects);

		let tableptype = document.createElement("th");
		tableptype.innerText = "Тип";
		tabletr.appendChild(tableptype);

		table.createTBody();

		let UpdatePairNums = function()
		{
			let npair = parseInt(SemesterSchedules.elements.schedule.dataset.startpair);

			for (let item of table.Items)
			{
				item.Row.PairNum = npair++;
				item.Row.PairNumText.innerText = item.Row.PairNum;
			}
		}

		Shared.MakeTableAsItemList(table, 4, 0, function() {
			let item = document.createElement("input");
			item.type = "checkbox";
			
			return item;
		}, function(row, grid, item) {
			item.title = "Не изменять эту пару нагрузки при автоматическом формировании расписания";

			grid.OnMove = function(grid2) { UpdatePairNums(); }

			let paircell = document.createElement("div");
			paircell.className = "schedule-load-pair-numcell";
			row.cells[1].appendChild(paircell);

			row.PairNumText = document.createElement("span");
			paircell.appendChild(row.PairNumText);

			let moveup = document.createElement("button");
			moveup.innerText = "▲";
			paircell.appendChild(moveup);
			moveup.addEventListener("click", function(event) { grid.Move(false); });

			let movedown = document.createElement("button");
			movedown.innerText = "▼";
			paircell.appendChild(movedown);
			movedown.addEventListener("click", function(event) { grid.Move(true); });

			let priorinfo = document.createElement("div");
			priorinfo.className = "schedule-load-pair-priorityinfo";

			let priorupdate = document.createElement("button");
			priorupdate.innerText = "Обновить";
			priorupdate.title = "Обновить сводку автоматического формирования для выбранного предмета";
			priorinfo.appendChild(priorupdate);

			let infoarea = document.createElement("textarea");
			priorinfo.appendChild(infoarea);
			infoarea.UpdateInfo = function(info)
			{
				row.PriorityInfo = info;
				infoarea.value = `Сводка по автоматическому формированию:
`;

				for (let data of info)
					infoarea.value += `
${data}`;
			}

			priorupdate.addEventListener("click", function(event) {
				let schedule = SemesterSchedules.GetValidatedSemesterSchedule();
				if (!schedule) return;

				priorupdate.disabled = true;

				Shared.RequestPOST("semesterschedule", schedule, {
					type: "priorityinfo",
					day: SemesterSchedules.WDayIndex,
					load: load.load.id,
					pair: row.PairNum,
					actdate: Header.GetActualDate(),
				}, function(data) {
					infoarea.UpdateInfo(data);
				}, null, null, function (status, message) {
					priorupdate.disabled = false;
				});
			});

			row.PairNumText.addEventListener("click", function(event) {
				if (priorinfo.contains(event.target)) return;

				priorinfo.hidden = !priorinfo.hidden && priorinfo.parentElement == row.cells[1];
				row.cells[1].appendChild(priorinfo);
			});

			if (item.PriorityInfo) infoarea.UpdateInfo(item.PriorityInfo);

			item.addEventListener("change", function(event) {
				if (item.checked) row.classList.add("schedule-load-pair-locked");
				else row.classList.remove("schedule-load-pair-locked");
			});
			
			if (item.checked) row.classList.add("schedule-load-pair-locked");

			let remove = grid.Remove
			grid.Remove = function()
			{
				if (!confirm("Удалить пару из расписания нагрузки группы? Следующие пары будут сдвинуты")) return;

				remove();
			}

			item.disabled = table.LoadLock.checked;

			let setupLecturersDisplay = function(button)
			{
				let request = null;
				let lectshow = false;

				button.addEventListener("change", function(event) {
					if (request) request.abort();

					lectshow = false;
				});

				button.addEventListener("mouseenter", function(event) {
					if (lectshow) return;
					lectshow = true;

					if (button.Value)
					{
						button.title = `${button.FullName}
						
(Получение преподавателей...)`;

						request = Shared.RequestGET("loadsubjectlecturers", {
							subject: button.Value,
							actdate: Header.GetActualDate(),
						}, function(data) {
							button.title = `${button.FullName}
`;

							for (let lect of data)
								button.title += `
${lect.lecturer.name} (${lect.main ? "Основной" : "Дополнительный"}) ${!lect.incount ? "(Не учитывается при формировании)" : ""}`;
						}, function (status, code, message) {
							button.title = `${button.FullName}
						
(Не удалось получить преподавателей: ${message})`;
						}, function (status, message) {
							button.title = `${button.FullName}
						
(Не удалось получить преподавателей: Ошибка HTTP ${status} - ${message})`;
						}, function (status, message) {
							request = null;
						});
					}
				});
			}

			row.PairSubject = Shared.ForeignKeyElement("YearGroupLoadSubjects", "ID", special);
			setupLecturersDisplay(row.PairSubject);
			row.PairSubject.hidden = true;
			row.cells[2].appendChild(row.PairSubject);

			row.HoursGrid = document.createElement("div");
			row.HoursGrid.className = "schedule-load-pair-hours";
			row.HoursGrid.hidden = true;
			row.cells[2].appendChild(row.HoursGrid);

			let hour1 = document.createElement("span");
			hour1.innerText = "Час 1";
			row.HoursGrid.appendChild(hour1);

			row.HoursGrid.Subject1 = Shared.ForeignKeyElement("YearGroupLoadSubjects", "ID", special);
			setupLecturersDisplay(row.HoursGrid.Subject1);
			row.HoursGrid.appendChild(row.HoursGrid.Subject1);

			let hour2 = document.createElement("span");
			hour2.innerText = "Час 2";
			row.HoursGrid.appendChild(hour2);

			row.HoursGrid.Subject2 = Shared.ForeignKeyElement("YearGroupLoadSubjects", "ID", special);
			setupLecturersDisplay(row.HoursGrid.Subject2);
			row.HoursGrid.appendChild(row.HoursGrid.Subject2);

			row.WeeksGrid = document.createElement("div");
			row.WeeksGrid.className = "schedule-load-pair-weeks";
			row.WeeksGrid.hidden = true;
			row.cells[2].appendChild(row.WeeksGrid);

			row.WeeksGrid.Subject1 = Shared.ForeignKeyElement("YearGroupLoadSubjects", "ID", special);
			setupLecturersDisplay(row.WeeksGrid.Subject1);
			row.WeeksGrid.Subject1.SetRequired(true);
			row.WeeksGrid.appendChild(row.WeeksGrid.Subject1);

			row.WeeksGrid.Subject2 = Shared.ForeignKeyElement("YearGroupLoadSubjects", "ID", special);
			setupLecturersDisplay(row.WeeksGrid.Subject2);
			row.WeeksGrid.Subject2.SetRequired(true);
			row.WeeksGrid.appendChild(row.WeeksGrid.Subject2);

			row.PType = document.createElement("select");
			row.cells[3].appendChild(row.PType);

			let ptype0 = document.createElement("option");
			ptype0.value = 0;
			ptype0.innerText = "Полная пара";
			row.PType.appendChild(ptype0);

			let ptype1 = document.createElement("option");
			ptype1.value = 1;
			ptype1.innerText = "По часу";
			row.PType.appendChild(ptype1);

			let ptype2 = document.createElement("option");
			ptype2.value = 2;
			ptype2.innerText = "По неделям";
			row.PType.appendChild(ptype2);

			row.PType.Update = function()
			{
				row.PairSubject.hidden = row.PType.value != 0;
				row.HoursGrid.hidden = row.PType.value != 1;
				row.WeeksGrid.hidden = row.PType.value != 2;
			}

			row.PType.addEventListener("change", function(event) {
				row.PType.Update();
			});

			switch (item.PType)
			{
				case 0: {
					row.PType.value = 0;
					if (item.Subject) row.PairSubject.SetValue(item.Subject);

					break;
				}
				case 1: case 2: case 3: {
					row.PType.value = 1;

					if (item.Subject && (item.PType == 1 || item.PType == 3)) row.HoursGrid.Subject1.SetValue(item.Subject);
					if (item.Subject && item.PType == 2 || item.Subject2 && item.PType == 3) row.HoursGrid.Subject2.SetValue(item.PType == 2 ? item.Subject : item.Subject2);

					break;
				}
				case 4: {
					row.PType.value = 2;

					if (item.Subject) row.WeeksGrid.Subject1.SetValue(item.Subject);
					if (item.Subject2) row.WeeksGrid.Subject2.SetValue(item.Subject2);

					break;
				}
				default: { row.PType.value = 0; break; }
			}

			UpdatePairNums();
			row.PType.Update();
		}, function(row, grid, item) {
			UpdatePairNums();
		});

		if (load.schedule)
		{
			let npair = parseInt(SemesterSchedules.elements.schedule.dataset.startpair);
			let last = npair - 1;

			for (let pair of Object.keys(load.schedule))
				if (parseInt(pair) > last) last = pair;

			for (let pnum = npair; pnum <= last; pnum++)
			{
				let pair = load.schedule[pnum];

				let item = document.createElement("input");
				item.type = "checkbox";

				if (pair)
				{
					item.checked = pair.Locked ?? false;
					item.PType = pair.type;
					item.Subject = pair.subject;
					item.Subject2 = pair.subject2;
					item.PriorityInfo = pair.priorityinfo;
				}

				table.AddItem(item);
			}
		}
	}

	static OpenSemesterSchedule(year, semester)
	{
		SemesterSchedules.lastmsg?.remove();
		let info = Shared.DisplayMessage(`Загружается расписание на ${year} год (семестр ${semester})...`, "warning");

		Shared.RequestGET("semesterschedule", {
			year: year,
			semester: semester,
			actdate: Header.GetActualDate(),
		}, function(data) {
			SemesterSchedules.LoadSemesterSchedule(data);
			SemesterSchedules.saved = true;

			SemesterSchedules.lastmsg?.remove();
			SemesterSchedules.lastmsg = Shared.DisplayMessage(`Загружено расписание на ${data.year} год (семестр ${data.semester})`, "success");
		}, function (status, code, message) {
			SemesterSchedules.lastmsg?.remove();
			SemesterSchedules.lastmsg = Shared.DisplayMessage(`Не удалось загрузить расписание на ${year} год (семестр ${semester}) (${message})`, "error");
		}, function (status, message) {
			SemesterSchedules.lastmsg?.remove();
			SemesterSchedules.lastmsg = Shared.DisplayMessage(`Не удалось загрузить расписание на ${year} год (семестр ${semester}) (Ошибка HTTP ${status}: ${message})`, "error");
		}, function (status, message) {
			info.remove();
		});
	}

	static LoadSemesterSchedule(schedule)
	{
		SemesterSchedules.Loads.splice(0);
		SemesterSchedules.elements.schedule.innerHTML = "";

		SemesterSchedules.elements.year.value = schedule.year;
		SemesterSchedules.elements.semester.value = schedule.semester;
		SemesterSchedules.elements.start.value = schedule.start;
		SemesterSchedules.elements.end.value = schedule.end;

		SemesterSchedules.WDays.splice(0);

		for (let [wday, data] of Object.entries(schedule.days))
			SemesterSchedules.WDays[wday] = data;

		SemesterSchedules.WDayIndex = null;
		SemesterSchedules.elements.wday.value = 0;
		SemesterSchedules.SwitchWDay(0);
	}

	static SwitchWDay(wday)
	{
		if (SemesterSchedules.WDayIndex != null)
			SemesterSchedules.WDays[SemesterSchedules.WDayIndex] = SemesterSchedules.ExportWDay();

		SemesterSchedules.ImportWDay(SemesterSchedules.WDays[wday]);
		SemesterSchedules.WDayIndex = wday;
	}

	static ImportWDay(wday)
	{
		SemesterSchedules.Loads.splice(0);
		SemesterSchedules.elements.schedule.innerHTML = "";

		if (wday)
		{
			wday.sort(function(a, b) { return a.load.name == b.load.name ? 0 : (a.load.name > b.load.name ? 1 : -1); })

			for (let load of wday)
				SemesterSchedules.AddLoad(load);
		}
	}

	static ExportWDay()
	{
		let data = [];

		for (let load of SemesterSchedules.Loads)
		{
			let ldata = {
				load: load.Load,
				Locked: load.LoadLock.checked,
				schedule: {},
			};

			for (let pair of load.Items)
			{
				let type = pair.Row.PType.value;
				let subject1 = type == 0 ? pair.Row.PairSubject : (type == 1 ? pair.Row.HoursGrid.Subject1 : pair.Row.WeeksGrid.Subject1);
				let subject2 = type == 0 ? null : (type == 1 ? pair.Row.HoursGrid.Subject2 : pair.Row.WeeksGrid.Subject2);

				let pdata = {
					Locked: pair.Item.checked,
					type: type,
					subject: subject1?.Value ? {id: subject1.Value, name: subject1.Name, fullname: subject1.FullName} : null,
					subject2: subject2?.Value ? {id: subject2.Value, name: subject2.Name, fullname: subject2.FullName} : null,
					priorityinfo: pair.Item.PriorityInfo,
				};

				if (type == 0)
				{
					pdata.type = 0;
					pdata.subject2 = null;
				}
				else if (pdata.type == 1)
				{
					pdata.type = 3;
					
					if (pdata.subject == null && pdata.subject2 == null)
						pdata.type = 0;
					else if (pdata.subject == null)
					{
						pdata.type = 2;
						pdata.subject = pdata.subject2;
						pdata.subject2 = null;
					}
					else if (pdata.subject2 == null)
						pdata.type = 1;
				}
				else if (pdata.type == 2)
				{
					pdata.type = 4;

					if (pdata.subject == null || pdata.subject2 == null)
						pdata.type = 0;

					if (pdata.subject == null)
					{ pdata.subject = pdata.subject2; pdata.subject2 = null; }
				}

				ldata.schedule[pair.Row.PairNum] = pdata;
			}

			data.push(ldata);
		}

		return data;
	}

	static GetValidatedSemesterSchedule(lock)
	{
		let year = SemesterSchedules.validateYear();
		if (!year) return;

		if (SemesterSchedules.WDayIndex != null)
			SemesterSchedules.WDays[SemesterSchedules.WDayIndex] = SemesterSchedules.ExportWDay();

		return SemesterSchedules.GetSemesterSchedule(lock);
	}

	static GetSemesterSchedule(lock)
	{
		let schedule = {
			year: SemesterSchedules.elements.year.value,
			semester: SemesterSchedules.getSemester(),
			start: SemesterSchedules.elements.start.value,
			end: SemesterSchedules.elements.end.value,
			days: {},
		};

		for (let [wday, ddata] of SemesterSchedules.WDays.entries())
		{
			let day = [];

			for (let ldata of ddata ?? [])
			{
				let load = {load: ldata.load, schedule: {}};

				for (let [pairn, pair] of Object.entries(ldata.schedule))
					if ((!lock || ldata.Locked || pair.Locked) && (pair.subject != null || pair.subject2 != null))
						load.schedule[pairn] = {
							subject: pair.subject,
							subject2: pair.subject2,
							type: pair.type,
						};
					else if (lock && (ldata.Locked || pair.Locked))
						load.schedule[pairn] = {};

				if (Object.keys(load.schedule).length > 0)
					day.push(load);
			}

			if (day.length > 0)
				schedule.days[wday] = day;
		}

		return schedule;
	}

	static SetupUnallocTable(data)
	{
		SemesterSchedules.elements.unalloc.hidden = data.length == 0;
		SemesterSchedules.elements.unalloc.tBodies[0].innerHTML = "";

		for (let unalloc of data)
		{
			let row = document.createElement("tr");
			SemesterSchedules.elements.unalloc.tBodies[0].appendChild(row);

			let group = document.createElement("td");
			group.innerText = unalloc.load.name;
			row.appendChild(group);

			let subject = document.createElement("td");
			subject.innerText = unalloc.subject.name;
			row.appendChild(subject);

			let hours = document.createElement("td");
			hours.innerText = unalloc.hours;
			row.appendChild(hours);
		}
	}

	static ExportSemesterSchedule(schedule)
	{
		let dialog = Shared.CreateDialog("Предпросмотр и экспорт расписания на семестр");

		let body = document.createElement("iframe");
		body.className = "modaldialog-export-frame";
		dialog.Body.appendChild(body);

		body.addEventListener("load", function(event) {
			let doc = body.contentDocument;

			if (!body.load)
			{
				doc.body.innerText = "Выберите нагрузку для экспорта конечного расписания";
				return;
			}

			doc.body.innerText = "Обработка конечного расписания для экспорта...";

			Shared.RequestPOST("export", schedule, {
				type: "semesterschedule",
				load: body.load,
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
					Shared.ExportTableToExcel(tab, "Расписание на семестр", `Расписание на ${schedule.year} год (семестр ${schedule.semester}) для ${data.load}`);
				});

				flex.appendChild(exportbtn);
				flex.appendChild(tab);

				let widetds = [];

				let titlerow = doc.createElement("tr");
				tab.appendChild(titlerow);

				let title = doc.createElement("th");
				title.innerText = "Нагрузка группы " + data.load;
				title.style.fontSize = "32px";
				title.style.fontWeight = "900";
				widetds.push(title);
				titlerow.appendChild(title);

				let spacerow = doc.createElement("tr");
				tab.appendChild(spacerow);

				let spacetd = doc.createElement("td");
				spacetd.style.height = "20px";
				widetds.push(spacetd);
				spacerow.appendChild(spacetd);

				let hrow = doc.createElement("tr");
				tab.appendChild(hrow);

				let ptd = doc.createElement("th");
				ptd.innerText = "Пара";
				ptd.style.fontWeight = "900";
				ptd.style.border = "2px solid black";
				hrow.appendChild(ptd);

				let daysn = ["Понедельник", "Вторник", "Среда", "Четверг", "Пятница", "Суббота", "Воскресенье"];
				let dayc = 0;

				for (let [d, cols] of Object.entries(data.days))
				{
					dayc++;

					let dtd = doc.createElement("th");
					dtd.innerText = daysn[d] ?? "";
					dtd.style.borderTop = dtd.style.borderBottom = "2px solid black";
					dtd.style.borderRight = dayc == Object.keys(data.days).length ? "2px solid black" : "1px solid black";
					dtd.style.fontWeight = "900";
					dtd.colSpan = cols;
					hrow.appendChild(dtd);

					for (let td of widetds)
						td.colSpan += cols;
				}

				let ldayr = {};
				let pairc = 0;
				for (let [p, pair] of Object.entries(data.pairs))
				{
					pairc++;

					ptd = doc.createElement("th");
					ptd.innerText = p;
					ptd.style.borderLeft = ptd.style.borderRight = "2px solid black";
					ptd.style.borderBottom = pairc == Object.keys(data.pairs).length ? "2px solid black" : "1px solid black";
					ptd.rowSpan = pair.count;

					for (let r = 0; r < pair.count; r++)
					{
						let rrow = doc.createElement("tr");
						tab.appendChild(rrow);

						if (!ptd.parentElement) rrow.appendChild(ptd);

						let ldayc = null;

						for (let [d, day] of Object.entries(pair.days))
						{
							ldayc = null;
							if (r == 0) ldayr[d] = {};

							for (let dc = 0; dc < data.days[d]; dc++)
								if (day[r][dc] != null)
								{
									let subj = doc.createElement("td");
									subj.innerText = day[r][dc];
									if (day[r][dc] == "") subj.style.height = "20px";
									subj.style.borderBottom = subj.style.borderRight = "1px solid black";
									rrow.appendChild(subj);
									
									ldayc = ldayr[d][dc] = subj;
								}
								else
								{
									if (ldayc && ldayc.colSpan == dc && ldayc.rowSpan == 1) { ldayc.colSpan++; ldayr[d][dc] = ldayc; }
									if (ldayr[d][dc] && ldayr[d][dc].rowSpan == r && ldayc != ldayr[d][dc] && ldayr[d][dc].colSpan <= dc + 1) { ldayr[d][dc].rowSpan++; ldayc = ldayr[d][dc]; } 
								}
						}
						
						if (ldayc) ldayc.style.borderRight = "2px solid black";
					}
				}

				for (let d of Object.values(ldayr))
						for (let c of Object.values(d))
							c.style.borderBottom = "2px solid black";

			}, function (status, code, message) {
				doc.body.innerText = `Не удалось обработать конечное расписание (${message})`;
			}, function (status, message) {
				doc.body.innerText = `Не удалось обработать конечное расписание (Ошибка HTTP ${status}: ${message})`;
			});
		});
		body.src = "about:blank";

		let loads = {};

		for (let day of Object.values(schedule.days))
			for (let load of day)
				if (!loads[load.load.id])
				{
					dialog.AddOption(load.load.name, function(event) { body.load = load.load.id; body.src = "about:blank"; });
					loads[load.load.id] = true;
				}
	
		dialog.Center();
	}

	static Initialize()
	{
		SemesterSchedules.elements.editorinfo.addEventListener("change", function(event) {
			SemesterSchedules.saved = false;
		});

		SemesterSchedules.elements.schedule.addEventListener("change", function(event) {
			SemesterSchedules.saved = false;
		});

		SemesterSchedules.elements.year.addEventListener("change", function(event) {
			SemesterSchedules.saved = false;

			let year = SemesterSchedules.validateYear();
			if (year) Shared.SetCookie("editor-semesterschedule-year", year);
		});

		SemesterSchedules.elements.add?.addEventListener("click", function(event) {
			let year = SemesterSchedules.validateYear();
			if (!year) return;

			let loads = [];
			for (let load of SemesterSchedules.Loads)
				loads.push(load.Load.id);

			Shared.SelectForeignKey("YearGroupLoads", function(row) {
				if (!row) return;

				let id = row.Cells.ID.Input.GetDataValue();

				for (let load of SemesterSchedules.Loads)
					if (load.Load.id == id)
					{
						Shared.DisplayMessage("Данная нагрузка уже есть в расписании", "error");
						return;
					}

				SemesterSchedules.AddLoad({load: {id: id, name: row.Name}});
			}, {Year: year}, [0, loads]);
		});

		SemesterSchedules.elements.clear?.addEventListener("click", function(event) {
			if (!SemesterSchedules.saved && !Shared.UnsavedConfirm()) return;

			SemesterSchedules.WDays.splice(0);

			SemesterSchedules.WDayIndex = null;
			SemesterSchedules.SwitchWDay(0);

			SemesterSchedules.saved = true;
		});

		SemesterSchedules.elements.open.addEventListener("click", function(event) {
			if (!SemesterSchedules.saved && !Shared.UnsavedConfirm()) return;

			let dialog = Shared.CreateDialog("Тип открытия");

			dialog.AddOption("Открыть из списка", function() {
				dialog.remove();

				Shared.QuerySelectList("Выберите год расписания на семестр", "semesterschedule", {actdate: Header.GetActualDate()}, function(value, item) {
					item.innerText = `Расписания на семестр на ${value} год`;
				}, function(value, item) {
					Shared.QuerySelectList("Выберите расписание по семестру", "semesterschedule", {actdate: Header.GetActualDate(), year: value}, function(value2, item2) {
						item2.innerText = `Расписание на ${value2} семестр`;
					}, function(value2, item2) {
						SemesterSchedules.OpenSemesterSchedule(value, value2);
					});
				});
			});

			dialog.AddOption("Открыть по году и семестру", function() {
				dialog.remove();

				let year = SemesterSchedules.validateYear();
				if (year) SemesterSchedules.OpenSemesterSchedule(year, SemesterSchedules.getSemester());
			});

			dialog.SetWidth(600);
			dialog.SetHeight(0);
			dialog.Center();
		});
		
		SemesterSchedules.elements.export.addEventListener("click", function(event) {
			let schedule = SemesterSchedules.GetValidatedSemesterSchedule();
			if (!schedule) return;

			SemesterSchedules.ExportSemesterSchedule(schedule);
		});

		SemesterSchedules.elements.update?.addEventListener("click", function(event) {
			if (!SemesterSchedules.saved && !Shared.UnsavedConfirm()) return;

			let lock = SemesterSchedules.GetValidatedSemesterSchedule(true);
			if (!lock) return;

			SemesterSchedules.lastmsg?.remove();
			let info = Shared.DisplayMessage(`Формируется расписание на ${lock.year} год (семестр ${lock.semester})...`, "warning");

			Shared.RequestPOST("semesterschedule", Object.keys(lock.days).length == 0 ? {} : lock, {
				type: "construct",
				year: lock.year,
				semester: lock.semester,
				actdate: Header.GetActualDate(),
			}, function(data) {
				SemesterSchedules.LoadSemesterSchedule(data);
				SemesterSchedules.saved = true;

				SemesterSchedules.lastmsg?.remove();
				SemesterSchedules.lastmsg = Shared.DisplayMessage(`Сформировано расписание на ${data.year} год (семестр ${data.semester})`, "success");
			}, function (status, code, message) {
				SemesterSchedules.lastmsg?.remove();
				SemesterSchedules.lastmsg = Shared.DisplayMessage(`Не удалось сформировать расписание на ${lock.year} год (семестр ${lock.semester}) (${message})`, "error");
			}, function (status, message) {
				SemesterSchedules.lastmsg?.remove();
				SemesterSchedules.lastmsg = Shared.DisplayMessage(`Не удалось сформировать расписание на ${lock.year} год (семестр ${lock.semester}) (Ошибка HTTP ${status}: ${message})`, "error");
			}, function (status, message) {
				info.remove();
			});
		});

		SemesterSchedules.elements.configure?.addEventListener("click", function(event) {
			let dialog = Shared.CreateDialog("Конфигурация влияния критериев приоритизации конструктора расписаний");

			let placeholder = document.createElement("div");
			placeholder.innerText = "Получение списка критериев приоритизации...";
			placeholder.className = "modaldialog-placeholder";
			dialog.Body.appendChild(placeholder);

			Shared.RequestGET("algorithmmodifiers", {
				type: "semesterschedule",
			}, function(data) {
				placeholder.remove();

				let body = document.createElement("div");
				body.className = "configuration-body";
				dialog.Body.appendChild(body);

				let LoadCategory = function(internal, name)
				{
					if (!data[internal]) return;

					let section = document.createElement("section");
					body.appendChild(section);

					let title = document.createElement("span");
					title.innerText = name;
					section.appendChild(title);

					let criterions = document.createElement("div");
					section.appendChild(criterions);

					for (let [id, criterion] of Object.entries(data[internal]))
					{
						let cookiename = `editor-semesterschedule-config-${internal}-${id}`;

						let crittitle = document.createElement("span");
						crittitle.innerText = criterion.name;
						crittitle.title = criterion.desc;
						criterions.appendChild(crittitle);
						
						if (criterion.min != null && criterion.max != null)
						{
							let range = document.createElement("input");
							range.type = "range";
							range.step = "0.1";
							range.min = criterion.min;
							range.max = criterion.max;
							range.value = Shared.GetCookie(cookiename) ?? 1;
							criterions.appendChild(range);

							let desc = document.createElement("span");
							desc.className = "configuration-percent";
							criterions.appendChild(desc);
							desc.Update = function() { desc.innerText = `${Math.round(range.value * 100)}%`; }
							desc.Update();

							range.addEventListener("input", function(event) {
								Shared.SetCookie(cookiename, range.value);
								desc.Update();
							});

							let reset = document.createElement("button");
							criterions.appendChild(reset);
							reset.innerText = "Сброс";
							reset.addEventListener("click", function(event) { 
								range.value = 1;
								Shared.SetCookie(cookiename, range.value);
								desc.Update();
							});
						}
						else
						{
							let info = document.createElement("span");
							info.className = "configuration-unmodifiable";
							info.innerText = "Не модифицируется";
							criterions.appendChild(info);
						}
					}
				}

				LoadCategory("main", "Основной алгоритм выбора предмета");
				LoadCategory("hourcount", "Алгоритм выбора количества часов");
				LoadCategory("hournum", "Алгоритм выбора номера часа");
			}, function(status, code, message) {
				placeholder.innerText = `Не удалось получить список критериев (${message})`;
			}, function (status, message) {
				placeholder.innerText = `Не удалось получить список критериев (Ошибка HTTP ${status}: ${message})`;
			});

			dialog.Center();
		});

		SemesterSchedules.elements.validate?.addEventListener("click", function(event) {
			let schedule = SemesterSchedules.GetValidatedSemesterSchedule();
			if (!schedule) return;

			SemesterSchedules.lastmsg?.remove();
			let info = Shared.DisplayMessage(`Идёт проверка расписания на ${schedule.year} год (семестр ${schedule.semester})...`, "warning");

			Shared.RequestPOST("semesterschedule", schedule, {
				type: "check",
				actdate: Header.GetActualDate(),
			}, function(data) {
				SemesterSchedules.lastmsg?.remove();

				if (data.length > 0)
				{
					SemesterSchedules.lastmsg = Shared.DisplayMessage(`Расписание на ${schedule.year} год (семестр ${schedule.semester}) не прошло проверку. Найдено нераспределенных пар: ${data.length}`, "error");
					SemesterSchedules.SetupUnallocTable(data);
				}
				else
					SemesterSchedules.lastmsg = Shared.DisplayMessage(`Расписание на ${schedule.year} год (семестр ${schedule.semester}) успешно прошло проверку`, "success");
			}, function (status, code, message) {
				SemesterSchedules.lastmsg?.remove();

				if (code >= 2)
					SemesterSchedules.lastmsg = Shared.DisplayMessage(`Расписание на ${schedule.year} год (семестр ${schedule.semester}) не прошло проверку (${message})`, "error");
				else
					SemesterSchedules.lastmsg = Shared.DisplayMessage(`Не удалось проверить расписание на ${schedule.year} год (семестр ${schedule.semester}) (${message})`, "error");
			}, function (status, message) {
				SemesterSchedules.lastmsg?.remove();
				SemesterSchedules.lastmsg = Shared.DisplayMessage(`Не удалось проверить расписание на ${schedule.year} год (семестр ${schedule.semester}) (Ошибка HTTP ${status}: ${message})`, "error");
			}, function (status, message) {
				info.remove();
			});
		});

		SemesterSchedules.elements.save?.addEventListener("click", function(event) {
			let schedule = SemesterSchedules.GetValidatedSemesterSchedule();
			if (!schedule || !confirm(`Сохранить текущее расписание на ${schedule.year} год (семестр ${schedule.semester})?`)) return;

			SemesterSchedules.lastmsg?.remove();
			let info = Shared.DisplayMessage(`Идёт сохранение расписания на ${schedule.year} год (семестр ${schedule.semester})...`, "warning");

			Shared.RequestPOST("semesterschedule", schedule, {
				type: "save",
				actdate: Header.GetActualDate(),
			}, function(data) {
				SemesterSchedules.lastmsg?.remove();

				if (data.length > 0)
				{
					SemesterSchedules.lastmsg = Shared.DisplayMessage(`Расписание на ${schedule.year} год (семестр ${schedule.semester}) не прошло проверку. Найдено нераспределенных пар: ${data.length}`, "error");
					SemesterSchedules.SetupUnallocTable(data);
				}
				else
				{
					SemesterSchedules.lastmsg = Shared.DisplayMessage(`Расписание на ${schedule.year} год (семестр ${schedule.semester}) успешно сохранено`, "success");
					SemesterSchedules.saved = true;
				}
			}, function (status, code, message) {
				SemesterSchedules.lastmsg?.remove();

				if (code >= 2)
					SemesterSchedules.lastmsg = Shared.DisplayMessage(`Расписание на ${schedule.year} год (семестр ${schedule.semester}) не прошло проверку (${message})`, "error");
				else
					SemesterSchedules.lastmsg = Shared.DisplayMessage(`Не удалось сохранить расписание на ${schedule.year} год (семестр ${schedule.semester}) (${message})`, "error");
			}, function (status, message) {
				SemesterSchedules.lastmsg?.remove();
				SemesterSchedules.lastmsg = Shared.DisplayMessage(`Не удалось сохранить расписание на ${schedule.year} год (семестр ${schedule.semester}) (Ошибка HTTP ${status}: ${message})`, "error");
			}, function (status, message) {
				info.remove();
			});
		});

		SemesterSchedules.elements.delete?.addEventListener("click", function(event) {
			let year = SemesterSchedules.validateYear();
			if (!year) return;

			let semester = SemesterSchedules.getSemester();
			if (!confirm(`Удалить расписание на ${year} (семестр ${semester})?`)) return;

			SemesterSchedules.lastmsg?.remove();
			let info = Shared.DisplayMessage(`Идёт удаление расписания на ${year} год (семестр ${semester})...`, "warning");

			Shared.RequestPOST("semesterschedule", {}, {
				type: "delete",
				actdate: Header.GetActualDate(),
				year: year,
				semester: semester,
			}, function(data) {
				SemesterSchedules.lastmsg?.remove();
				SemesterSchedules.lastmsg = Shared.DisplayMessage(`Расписание на ${year} год (семестр ${semester}) успешно удалено`, "success");
			}, function (status, code, message) {
				SemesterSchedules.lastmsg?.remove();
				SemesterSchedules.lastmsg = Shared.DisplayMessage(`Не удалось удалить расписание на ${year} год (семестр ${semester}) (${message})`, "error");
			}, function (status, message) {
				SemesterSchedules.lastmsg?.remove();
				SemesterSchedules.lastmsg = Shared.DisplayMessage(`Не удалось удалить расписание на ${year} год (семестр ${semester}) (Ошибка HTTP ${status}: ${message})`, "error");
			}, function (status, message) {
				info.remove();
			});
		});

		SemesterSchedules.elements.wday.addEventListener("change", function(event) {
			SemesterSchedules.SwitchWDay(SemesterSchedules.elements.wday.value);
		});

		SemesterSchedules.elements.unallocclose.addEventListener("click", function(event) {
			SemesterSchedules.elements.unalloc.hidden = true;
		});

		onbeforeunload = function() {
			if (!SemesterSchedules.saved)
				return "Расписание не сохранено";
		}

		if (Shared.GetCookie("editor-semesterschedule-year"))
			SemesterSchedules.elements.year.value = Shared.GetCookie("editor-semesterschedule-year");

		let year = SemesterSchedules.validateYear();
		if (year) SemesterSchedules.OpenSemesterSchedule(year, SemesterSchedules.getSemester());

		if (SemesterSchedules.elements.save)
			window.addEventListener("keydown", function(event) {
				if (!event.ctrlKey || event.code != "KeyS") return;

				event.preventDefault();

				SemesterSchedules.elements.save.click();
			});
	}
}
SemesterSchedules.Initialize();