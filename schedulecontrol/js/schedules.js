class Schedules
{
	static elements = Shared.MapElements({
		add: "#add-btn",
		clear: "#clear-btn",
		open: "#open-btn",
		view: "#view-btn",
		export: "#export-btn",
		update: "#update-btn",
		configure: "#configure-btn",
		validate: "#validate-btn",
		save: "#save-btn",
		delete: "#delete-btn",
		editorinfo: "#editor-info",
		bells: "#editor-info-bells-label",
		date: "#editor-info-date",
		schedule: "#schedule-container",
	});

	static bells = Shared.ForeignKeyElement("BellsSchedules", "ID");
	static Groups = [];

	static saved = true;
	static lastmsg = null;

	static AutoGenLast = null;
	static AutoGenActive = false;

	static validateDate()
	{
		if (!Schedules.elements.date.reportValidity())
			return false;

		let value = Schedules.elements.date.value;
		if (value == "")
		{
			Shared.DisplayMessage("Неверный формат даты", "error");
			return false;
		}

		return Shared.DateToSql(new Date(value), false);
	}

	static validateBells()
	{
		if (Schedules.bells.Value == null)
		{
			Shared.DisplayMessage("Некорректное расписание звонков", "error");
			return false;
		}

		return {id: Schedules.bells.Value, name: Schedules.bells.Name};
	}

	static DateString(date)
	{
		return new Date(date).toLocaleDateString("ru-RU", {weekday: "short", year: "numeric", month: "long", day: "numeric"});
	}

	static AddGroup(group)
	{
		let table = document.createElement("table");
		Schedules.elements.schedule.appendChild(table);
		Schedules.Groups.push(table);

		let colgroup = document.createElement("colgroup");
		table.appendChild(colgroup);

		let colinfo = document.createElement("col");
		colinfo.className = "schedule-group-col-info";
		colinfo.span = 3;
		colgroup.appendChild(colinfo);

		let colsubj = document.createElement("col");
		colsubj.className = "schedule-group-col-subject";
		colgroup.appendChild(colsubj);

		let colroom = document.createElement("col");
		colroom.className = "schedule-group-col-rooms";
		colgroup.appendChild(colroom);

		let collect = document.createElement("col");
		collect.className = "schedule-group-col-lecturers";
		colgroup.appendChild(collect);

		let special = [1, function() { return {group: group.group.id, date: Schedules.validateDate()}; }];

		table.Group = group.group;
		let thead = table.createTHead();

		let grouptr = document.createElement("tr");
		thead.appendChild(grouptr);

		let grouplockth = document.createElement("th");
		grouptr.appendChild(grouplockth);

		let grouplockgrid = document.createElement("div");
		grouplockgrid.className = "schedule-group-lock-grid";
		grouplockth.appendChild(grouplockgrid);

		let deletegroup = document.createElement("button");
		deletegroup.className = "schedule-group-delete";
		deletegroup.innerText = "✕";
		deletegroup.addEventListener("click", function(event) {
			if (!confirm("Удалить учебную группу из расписания?")) return;

			table.remove();
			Shared.RemoveFromArray(Schedules.Groups, table);
		});
		grouplockgrid.appendChild(deletegroup);

		table.GroupLock = document.createElement("input");
		table.GroupLock.type = "checkbox";
		table.GroupLock.checked = group.Locked ?? false;
		table.GroupLock.title = "Не изменять расписание этой группы при автоматическом формировании расписания";
		grouplockgrid.appendChild(table.GroupLock);

		table.GroupLock.addEventListener("change", function(event) {
			if (table.GroupLock.checked) table.classList.add("schedule-group-locked");
			else table.classList.remove("schedule-group-locked");

			for (let item of table.Items)
				item.Item.disabled = table.GroupLock.checked;
		});

		if (table.GroupLock.checked) table.classList.add("schedule-group-locked");

		let groupnameth = document.createElement("th");
		groupnameth.colSpan = 7;
		grouptr.appendChild(groupnameth);

		let groupnamegrid = document.createElement("th");
		groupnamegrid.className = "schedule-group-name-grid";
		groupnamegrid.colSpan = 7;
		groupnameth.appendChild(groupnamegrid);
		
		let groupname = document.createElement("span");
		groupname.innerText = group.group.name;
		groupnamegrid.appendChild(groupname);

		let groupcollapse = document.createElement("button");
		groupcollapse.innerText = "▼";
		groupcollapse.title = "Свернуть/развернуть";
		groupnamegrid.appendChild(groupcollapse);
		groupcollapse.addEventListener("click", function(event) {
			groupcollapse.Collapsed = !groupcollapse.Collapsed;
			groupcollapse.innerText = groupcollapse.Collapsed ? "▲" : "▼";

			if (groupcollapse.Collapsed) table.classList.add("schedule-group-collapsed");
			else table.classList.remove("schedule-group-collapsed");
		});

		let tabletr = document.createElement("tr");
		thead.appendChild(tabletr);

		let tablelock = document.createElement("th");
		tablelock.innerText = "Блок";
		tabletr.appendChild(tablelock);

		let tablepair = document.createElement("th");
		tablepair.innerText = "Номер пары";
		tabletr.appendChild(tablepair);

		let tablehours = document.createElement("th");
		tablehours.innerText = "Номер часа";
		tabletr.appendChild(tablehours);

		let tableoccupation = document.createElement("th");
		tableoccupation.innerText = "Занятие";
		tabletr.appendChild(tableoccupation);

		let tablelects = document.createElement("th");
		tablelects.innerText = "Преподаватели";
		tabletr.appendChild(tablelects);

		let tablerooms = document.createElement("th");
		tablerooms.className = "schedule-group-lectrooms-roomcell";
		tablerooms.innerText = "Кабинеты";
		tabletr.appendChild(tablerooms);

		table.createTBody();

		let UpdatePairHourNums = function()
		{
			let hour = parseInt(Schedules.elements.schedule.dataset.starthour);

			for (let item of table.Items)
			{
				item.Row.Hour = hour;

				if (!item.Row.Hour2.hidden && hour % 2 == 0)
					item.Row.ShowHour2(false);

				item.Row.UpdateText();

				hour += hour % 2 == 0 ? 1 : 2;
			}
		}

		Shared.MakeTableAsItemList(table, 6, 0, function() {
			let item = document.createElement("input");
			item.type = "checkbox";

			return item;
		}, function(row, grid, item) {
			item.title = "Не изменять расписание этой пары при автоматическом формировании расписания";

			row.Hour2 = document.createElement("tr");
			row.after(row.Hour2);

			for (let i = 0; i < 3; i++)
				row.Hour2.appendChild(document.createElement("td"));

			item.addEventListener("change", function(event) {
				if (item.checked)
				{ row.classList.add("schedule-group-hour-locked"); row.Hour2.classList.add("schedule-group-hour-locked"); }
				else
				{ row.classList.remove("schedule-group-hour-locked"); row.Hour2.classList.remove("schedule-group-hour-locked"); }
			});

			if (item.locked)
			{ row.classList.add("schedule-group-hour-locked"); row.Hour2.classList.add("schedule-group-hour-locked"); }

			let remove = grid.Remove
			grid.Remove = function()
			{
				if (!confirm("Удалить пару из расписания группы? Следующие пары будут сдвинуты")) return;

				row.Hour2.remove();
				remove();
			}

			grid.OnMove = function(grid2)
			{
				row.after(row.Hour2);
				grid2.Row.after(grid2.Row.Hour2);

				UpdatePairHourNums();
			}

			let paircell = document.createElement("div");
			paircell.className = "schedule-group-pair-numcell";
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

			row.Hour = 0;

			row.PairNumText.addEventListener("click", function(event) {
				event.preventDefault();

				row.ShowHour2(row.Hour2.hidden && row.Hour % 2 == 1);
			});

			for (let h = 0; h < 2; h++)
			{
				let crow = h == 0 ? row : row.Hour2;
				let cstrt = h == 0 ? 2 : 0;
				let data = h == 0 ? item : item.Hour2;

				crow.HourCell = crow.cells[cstrt];

				let hourcell = document.createElement("div");
				hourcell.className = "schedule-group-hour-numcell";
				crow.HourCell.appendChild(hourcell);

				crow.HourCellText = document.createElement("span");
				hourcell.appendChild(crow.HourCellText);
				
				crow.HourCellMove = document.createElement("button");
				crow.HourCellMove.innerText = h == 0 ? "▼" : "▲";
				hourcell.appendChild(crow.HourCellMove);

				crow.HourCellMove.addEventListener("click", function(event) {
					let row2 = row == crow ? row.Hour2 : row;

					let occ = crow.Occupation.Value ? {id: crow.Occupation.Value, name: crow.Occupation.Name, fullname: crow.Occupation.FullName} : null;
					let occ2 = row2.Occupation.Value ? {id: row2.Occupation.Value, name: row2.Occupation.Name, fullname: row2.Occupation.FullName} : null;
					let occact = crow.Occupation.Activity;
					let occact2 = row2.Occupation.Activity;

					let lectrooms = [];
					let lectrooms2 = [];

					for (let item of crow.LectRooms.Items)
						lectrooms.push({
							lect: item.Item.Value ? {id: item.Item.Value, name: item.Item.Name, fullname: item.Item.FullName} : null,
							room: item.Row.Room.Value ? {id: item.Row.Room.Value, name: item.Row.Room.Name, fullname: item.Row.Room.FullName} : null,
							id: item.Item.LectRoomID,
						});

					for (let item of row2.LectRooms.Items)
						lectrooms2.push({
							lect: item.Item.Value ? {id: item.Item.Value, name: item.Item.Name, fullname: item.Item.FullName} : null,
							room: item.Row.Room.Value ? {id: item.Row.Room.Value, name: item.Row.Room.Name, fullname: item.Row.Room.FullName} : null,
							id: item.Item.LectRoomID,
						});

					crow.Occupation.SetValue(occ2);
					row2.Occupation.SetValue(occ);
					crow.Occupation.Activity = occact2;
					row2.Occupation.Activity = occact;

					crow.LectRooms.ClearItems();
					for (let lectroom of lectrooms2)
					{
						let lroom = crow.LectRooms.NewItem();
						lroom.SetValue(lectroom.lect);
						lroom.Room = lectroom.room;
						lroom.LectRoomID = lectroom.id;

						crow.LectRooms.AddItem(lroom);
					}

					row2.LectRooms.ClearItems();
					for (let lectroom of lectrooms)
					{
						let lroom = row2.LectRooms.NewItem();
						lroom.SetValue(lectroom.lect);
						lroom.Room = lectroom.room;
						lroom.LectRoomID = lectroom.id;

						row2.LectRooms.AddItem(lroom);
					}
				});

				let priorinfo = document.createElement("div");
				priorinfo.className = "schedule-group-hour-priorityinfo";

				let priorupdate = document.createElement("button");
				priorupdate.innerText = "Обновить";
				priorupdate.title = "Обновить сводку автоматического формирования для выбранной занятости";
				priorinfo.appendChild(priorupdate);

				let infoarea = document.createElement("textarea");
				priorinfo.appendChild(infoarea);
				infoarea.UpdateInfo = function(info)
				{
					crow.PriorityInfo = info;
					infoarea.value = `Сводка по автоматическому формированию:
`;

					for (let data of info)
						infoarea.value += `
${data}`;
				}

				priorupdate.addEventListener("click", function(event) {
					let schedule = Schedules.GetValidatedSchedule();
					if (!schedule) return;

					priorupdate.disabled = true;

					Shared.RequestPOST("schedule", schedule, {
						type: "priorityinfo",
						group: group.group.id,
						hour: row.Hour + h,
						actdate: Header.GetActualDate(),
					}, function(data) {
						infoarea.UpdateInfo(data);
					}, null, null, function (status, message) {
						priorupdate.disabled = false;
					});
				});

				crow.HourCellText.addEventListener("click", function(event) {
					if (priorinfo.contains(event.target)) return;

					priorinfo.hidden = !priorinfo.hidden && priorinfo.parentElement == crow.HourCell;
					crow.HourCell.appendChild(priorinfo);
				});

				if (data?.PriorityInfo)
					infoarea.UpdateInfo(data.PriorityInfo);

				crow.Occupation = Shared.ForeignKeyElement("", "");
				crow.Occupation.removeEventListener("click", crow.Occupation.clickevent);

				if (data?.Occupation) crow.Occupation.SetValue(data.Occupation);
				crow.Occupation.Activity = data?.Activity ?? false;

				crow.cells[cstrt + 1].appendChild(crow.Occupation);

				let lectroomgrid = document.createElement("div");
				crow.cells[cstrt + 2].className = "schedule-group-lectrooms-td";
				crow.cells[cstrt + 2].colSpan = 2;
				crow.cells[cstrt + 2].appendChild(lectroomgrid);

				crow.LectRooms = document.createElement("table");
				crow.LectRooms.className = "schedule-group-lectrooms";
				crow.LectRooms.createTBody();

				let lectrooms = crow.LectRooms;
				lectrooms.NewItem = function()
				{
					let room = Shared.ForeignKeyElement("Lecturers", "ID", null, [3, function() {
						let lecturers = [];

						for (let item of lectrooms.Items)
							if (item.Item.Value)
								lecturers.push(item.Item.Value);

						return {group: group.group.id, date: Schedules.validateDate(), filter: lecturers};
					}]);
					room.SetRequired(true);

					return room;
				}

				let autolectrooms = document.createElement("button");
				autolectrooms.innerText = "Автоопределение";
				autolectrooms.title = "Определить автоматически на основе закреплений и занятости";
				autolectrooms.addEventListener("click", function(event) {
					let schedule = Schedules.GetValidatedSchedule();
					if (!schedule) return;

					autolectrooms.disabled = true;

					Shared.RequestPOST("schedule", schedule, {
						type: "lectrooms",
						group: group.group.id,
						hour: row.Hour + h,
						actdate: Header.GetActualDate(),
					}, function(data) {
						autolectrooms.hidden = false;
						lectrooms.ClearItems();

						for (let lectroom of data)
						{
							let lroom = lectrooms.NewItem();
							lroom.SetValue(lectroom.lecturer);
							lroom.Room = lectroom.room;
							lroom.LectRoomID = lectroom.id;

							lectrooms.AddItem(lroom);
						}
					}, null, null, function (status, message) {
						autolectrooms.disabled = false;
					});
				});
				lectroomgrid.appendChild(autolectrooms);
				lectroomgrid.appendChild(crow.LectRooms);

				crow.Occupation.addEventListener("click", function(event) {
					let dialog = Shared.CreateDialog("Выберите тип занятия");

					let container = document.createElement("div");
					container.className = "schedule-group-occupation-dialog-container";
					dialog.Body.appendChild(container);

					let select = function(type)
					{
						dialog.remove();

						if (type == 0 || type == 1)
						{
							Shared.SelectForeignKey(type == 0 ? "YearGroupLoadSubjects" : "Activities", function(srow) {
								let value = srow?.Cells.ID.Input.GetDataValue();

								crow.Occupation.SetValue(value ? {id: value, name: srow.Name, fullname: srow.FullName} : null);
								crow.Occupation.Activity = type == 1;

								lectrooms.ClearItems();
								autolectrooms.click();
							}, null, [special[0], special[1]()]);
						}
						else
						{
							crow.Occupation.SetValue();
							lectrooms.ClearItems();
						}
					}

					let subject = document.createElement("input");
					subject.type = "button";
					subject.value = "Предмет";
					subject.addEventListener("click", function(event2) { select(0); });
					container.appendChild(subject);

					let activity = document.createElement("input");
					activity.type = "button";
					activity.value = "Деятельность";
					activity.addEventListener("click", function(event2) { select(1); });
					container.appendChild(activity);

					let clear = document.createElement("input");
					clear.type = "button";
					clear.value = "Нет занятия";
					clear.addEventListener("click", function(event2) { select(2); });
					container.appendChild(clear);

					dialog.SetWidth(1000);
					dialog.SetHeight(110);
					dialog.Center();
				});

				Shared.MakeTableAsItemList(lectrooms, 2, 0, function() {
					let item = lectrooms.NewItem();
					item.click();

					return item;
				}, function(row, grid, item) {
					autolectrooms.hidden = true;

					row.cells[1].className = "schedule-group-lectrooms-roomcell";

					row.Room = Shared.ForeignKeyElement("Rooms", "ID", null, [3, function() {
						let rooms = [];

						for (let item of lectrooms.Items)
							if (item.Row.Room.Value)
								rooms.push(item.Row.Room.Value);

						return {group: group.group.id, date: Schedules.validateDate(), filter: rooms};
					}]);

					row.Room.SetRequired(true);
					if (item.Room) row.Room.SetValue(item.Room);
					row.cells[1].appendChild(row.Room);
				}, function(row, grid, item) {
					autolectrooms.hidden = lectrooms.Items.length > 0;
				}, true);

				if (data?.LectRooms)
					for (let lectroom of data.LectRooms)
					{
						let lroom = lectrooms.NewItem();
						lroom.SetValue(lectroom.lecturer);
						lroom.Room = lectroom.room;
						lroom.LectRoomID = lectroom.id;

						lectrooms.AddItem(lroom);
					}
			}

			row.ShowHour2 = function(show)
			{
				row.Hour2.hidden = !show;
				row.cells[0].rowSpan = row.cells[1].rowSpan = show ? 2 : 1;
			
				if (show) row.HourCell.classList.remove("schedule-group-hour-aspair");
				else row.HourCell.classList.add("schedule-group-hour-aspair");

				row.UpdateText();
			}

			row.UpdateText = function()
			{
				let hour = row.Hour;
				row.PairNumText.innerText = hour % 2 == 0 ? hour / 2 : (hour + 1) / 2;
				
				if (row.Hour2.hidden)
					row.HourCellText.innerText = hour % 2 == 0 ? hour : `${hour} - ${hour + 1}`;
				else
				{
					row.HourCellText.innerText = hour;
					row.Hour2.HourCellText.innerText = hour + 1;
				}
			}

			row.cells[5].remove();

			UpdatePairHourNums();
			row.ShowHour2(false);

			if (row.Occupation.Value != row.Hour2.Occupation.Value || row.Occupation.Activity != row.Hour2.Occupation.Activity || row.LectRooms.Items.length != row.Hour2.LectRooms.Items.length)
				row.ShowHour2(true);
			else
				for (let [id, lroom] of row.LectRooms.Items.entries())
				{
					let lroom2 = row.Hour2.LectRooms.Items[id];

					if (lroom.Item.Value != lroom2.Item.Value || lroom.Row.Room.Value != lroom2.Row.Room.Value)
					{ row.ShowHour2(true); break; }
				}
		}, function(row, grid, item) { UpdatePairHourNums(); });

		tablehours.className = "schedule-group-hour";
		tablehours.addEventListener("click", function(event) {
			event.preventDefault();

			tablehours.Show = !tablehours.Show;

			for (let item of table.Items)
				item.Row.ShowHour2(tablehours.Show && item.Row.Hour % 2 == 1);
		})

		if (group.schedule)
		{
			let shour = parseInt(Schedules.elements.schedule.dataset.starthour);
			let lhour = shour - 1;

			for (let hour of Object.keys(group.schedule))
				if (hour > lhour) lhour = parseInt(hour);

			for (let hour = shour; hour <= lhour; hour += hour % 2 == 0 ? 1 : 2)
			{
				let item = document.createElement("input");
				item.type = "checkbox";
				item.Hour2 = {};

				for (let h = 0; h < (hour % 2 == 0 ? 1 : 2); h++)
				{
					let data = h == 0 ? item : item.Hour2;
					let hdata = group.schedule[hour + h];

					if (hdata)
					{
						data.Activity = hdata.type == 1;
						data.Occupation = hdata.occupation;
						data.LectRooms = hdata.lectrooms;
						data.PriorityInfo = hdata.priorityinfo;
					}
				}

				table.AddItem(item);
			}
		}
	}

	static LoadSchedule(schedule)
	{
		Schedules.elements.date.value = schedule.date;
		Schedules.bells.SetValue(schedule.bells);

		Schedules.elements.schedule.innerHTML = "";
		Schedules.Groups.splice(0);

		schedule.groups.sort(function(a, b) { return a.group.name == b.group.name ? 0 : (a.group.name > b.group.name ? 1 : -1); })

		for (let group of schedule.groups)
			Schedules.AddGroup(group);
	}

	static GetValidatedSchedule(lock)
	{
		if (!Schedules.validateDate() || !Schedules.validateBells()) return;

		return Schedules.GetSchedule(lock);
	}

	static GetSchedule(lock)
	{
		let schedule = {
			date: Schedules.elements.date.value,
			bells: {id: Schedules.bells.Value, name: Schedules.bells.Name},
			groups: [],
		};

		for (let group of Schedules.Groups)
		{
			let gdata = {
				group: group.Group,
				schedule: {},
			};

			for (let item of group.Items)
			{
				if (lock && !group.GroupLock.checked && !item.Item.checked) continue;

				for (let h = item.Row.Hour; h <= (item.Row.Hour % 2 == 0 ? item.Row.Hour : item.Row.Hour + 1); h++)
				{
					let value = h == item.Row.Hour || item.Row.Hour2.hidden ? item.Row : item.Row.Hour2;

					let hour = {
						type: value.Occupation.Value ? (value.Occupation.Activity ? 1 : 0) : 2,
						occupation: value.Occupation.Value ? {id: value.Occupation.Value, name: value.Occupation.Name, fullname: value.Occupation.FullName} : null,
						lectrooms: [],
						priorityinfo: value.PriorityInfo,
					};

					if (hour.occupation != null)
						for (let lroom of value.LectRooms.Items)
							if (lroom.Item.Value && lroom.Row.Room.Value)
								hour.lectrooms.push({
									id: lroom.Item.LectRoomID ?? null,
									lecturer: {id: lroom.Item.Value, name: lroom.Item.Name},
									room: {id: lroom.Row.Room.Value, name: lroom.Row.Room.Name},
								});

					gdata.schedule[h] = hour;
				}
			}

			if (!lock || group.GroupLock.checked || Object.keys(gdata.schedule).length > 0)
				schedule.groups.push(gdata);
		}

		return schedule;
	}

	static OpenSchedule(date)
	{
		Schedules.lastmsg?.remove();
		let info = Shared.DisplayMessage(`Загружается расписание занятий (${Schedules.DateString(date)})...`, "warning");

		Shared.RequestGET("schedule", {
			date: date,
			actdate: Header.GetActualDate(),
		}, function(data) {
			Schedules.LoadSchedule(data);
			Schedules.saved = true;

			Schedules.lastmsg?.remove();
			Schedules.lastmsg = Shared.DisplayMessage(`Загружено расписание занятий (${Schedules.DateString(data.date)})`, "success");
		}, function (status, code, message) {
			Schedules.lastmsg?.remove();
			Schedules.lastmsg = Shared.DisplayMessage(`Не удалось загрузить расписание занятий (${Schedules.DateString(date)}) (${message})`, "error");
		}, function (status, message) {
			Schedules.lastmsg?.remove();
			Schedules.lastmsg = Shared.DisplayMessage(`Не удалось загрузить расписание занятий (${Schedules.DateString(date)}) (Ошибка HTTP ${status}: ${message})`, "error");
		}, function (status, message) {
			info.remove();
		});
	}

	static ExportSchedule(schedule)
	{
		let dialog = Shared.CreateDialog("Предпросмотр и экспорт расписания");

		let body = document.createElement("iframe");
		body.className = "modaldialog-export-frame";
		dialog.Body.appendChild(body);
		
		body.addEventListener("load", function(event) {
			let doc = body.contentDocument;
			doc.body.innerText = "Обработка конечного расписания для экспорта...";

			let lect = body.LectSchedule;

			Shared.RequestPOST("export", schedule, {
				type: lect ? "lectschedule" : "schedule",
				actdate: Header.GetActualDate(),
			}, function(data) {
				doc.body.innerHTML = `<style>
					table, tr, th { vertical-align: middle; text-align: center; font-family: "Times New Roman"; }
					td { height: 18px; }
				</style>`;

				if (lect)
				{
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
						Shared.ExportTableToExcel(tab, "Расписание преподавателей", `Расписание преподавателей (${data.date})`);
					});

					flex.appendChild(exportbtn);
					flex.appendChild(tab);

					let hrow = doc.createElement("tr");
					tab.appendChild(hrow);

					let d = Shared.DateFromSql(data.date);
					let hdate = doc.createElement("th");
					hdate.innerText = `${d.toLocaleDateString("ru-RU", {day: "numeric", month: "long"})} (${d.toLocaleDateString("ru-RU", {weekday: "long"})})`;
					hdate.style.border = "2px solid black";
					hdate.style.fontWeight = "900";
					hdate.colSpan = 2;
					hrow.appendChild(hdate);

					let hpairs = doc.createElement("th");
					hpairs.innerText = "Распределение групп по парам";
					hpairs.style.borderTop = hpairs.style.borderBottom = hpairs.style.borderRight = "2px solid black";
					hpairs.style.fontWeight = "900";
					hpairs.colSpan = data.pairs.length;
					hrow.appendChild(hpairs);

					let lprow = doc.createElement("tr");
					tab.appendChild(lprow);

					let lpnum = doc.createElement("td");
					lpnum.innerText = "№";
					lpnum.style.borderLeft = lpnum.style.borderBottom = lpnum.style.borderRight = "2px solid black";
					lpnum.style.fontWeight = "900";
					lprow.appendChild(lpnum);

					let lplect = doc.createElement("td");
					lplect.innerText = "Преподаватели";
					lplect.style.borderBottom = lplect.style.borderRight = "2px solid black";
					lplect.style.fontWeight = "900";
					lprow.appendChild(lplect);

					for (let [p, pair] of data.pairs.entries())
					{
						let lppair = doc.createElement("td");
						lppair.innerText = pair;
						lppair.style.borderBottom = "2px solid black";
						lppair.style.borderRight = p == data.pairs.length - 1 ? "2px solid black" : "1px solid black";
						lppair.style.fontWeight = "900";
						lprow.appendChild(lppair);
					}

					for (let [l, lecturer] of data.lecturers.entries())
					{
						let num = doc.createElement("th");
						num.innerText = l + 1;
						num.style.borderLeft = num.style.borderRight = "2px solid black";
						num.style.borderBottom = l == data.lecturers.length - 1 ? "2px solid black" : "1px solid black";
						num.style.fontWeight = "900";
						num.rowSpan = lecturer.hours;

						let lect = doc.createElement("th");
						lect.innerText = lecturer.lecturer;
						lect.style.textAlign = "left";
						lect.style.borderRight = "2px solid black";
						lect.style.borderBottom = l == data.lecturers.length - 1 ? "2px solid black" : "1px solid black";
						lect.style.fontWeight = "900";
						lect.rowSpan = lecturer.hours;

						let phours = {};

						for (let h = 0; h < lecturer.hours; h++)
						{
							let lrow = doc.createElement("tr");
							tab.appendChild(lrow);

							if (!num.parentElement) lrow.appendChild(num);
							if (!lect.parentElement) lrow.appendChild(lect);

							let pcount = 0;
							for (let [p, pair] of Object.entries(lecturer.pairs))
							{
								let hour = pair[h];
								pcount++;

								if (hour != null)
								{
									let ph/* ;

									if (phours[p - 1] && hour == phours[p - 1].innerText && phours[p - 1].rowSpan == 1)
									{
										ph = phours[p - 1];
										ph.colSpan++;
									}
									else
									{
										ph */ = doc.createElement("td");
										ph.innerText = hour;
										ph.style.borderBottom = "1px solid black";
										lrow.appendChild(ph);
									// }

									ph.style.borderRight = pcount == Object.keys(lecturer.pairs).length ? "2px solid black" : "1px solid black";

									phours[p] = ph;
								}
								else if (phours[p])
								{
									/* let lastspan = true;

									for (let i = p; i >= p - (phours[p].colSpan - 1); i--)
										if (phours[i] !== phours[p]) { lastspan = false; break; } */

									phours[p].rowSpan++;

									/* for (let pp = p - 1; lecturer.pairs[pp]; pp--)
										if (phours[pp] && phours[pp].rowSpan == phours[p].rowSpan && phours[pp].innerText == phours[p].innerText)
										{ phours[pp].colSpan++; phours[p].remove(); phours[p] = phours[pp]; }
										else break; */
								}
							}
						}

						if (l == data.lecturers.length - 1)
							for (let phour of Object.values(phours))
								phour.style.borderBottom = "2px solid black";
					}
				}
				else
				{
					doc.body.style.width = "max-content";
					doc.body.style.display = "grid";
					doc.body.style.gridTemplateColumns = "1fr 1fr";
					doc.body.style.alignItems = "start";
					doc.body.style.columnGap = "1rem";
					doc.body.style.rowGap = "1rem";
					
					for (let areainfo of data)
					{
						let flex = doc.createElement("div");
						flex.style.display = "flex";
						flex.style.flexDirection = "column";
						flex.style.alignItems = "center";
						flex.style.rowGap = "20px";
						doc.body.appendChild(flex);

						let tab = doc.createElement("table");
						tab.style.borderCollapse = "collapse";
						tab.style.textAlign = "center";
						tab.style.verticalAlign = "middle";
						tab.style.fontFamily = "Times New Roman";

						let exportbtn = doc.createElement("button");
						exportbtn.innerText = "Сохранить в Excel";
						exportbtn.addEventListener("click", function(event) {
							Shared.ExportTableToExcel(tab, areainfo.title, `${areainfo.title} (${areainfo.date})`);
						});

						flex.appendChild(exportbtn);
						flex.appendChild(tab);

						let widetds = [];

						let titlerow = doc.createElement("tr");
						tab.appendChild(titlerow);

						let title = doc.createElement("th");
						title.innerText = areainfo.title;
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

						let daterow = doc.createElement("tr");
						tab.appendChild(daterow);

						let d = Shared.DateFromSql(areainfo.date);
						let date = doc.createElement("th");
						date.innerText = `на ${d.toLocaleDateString("ru-RU", {day: "numeric", month: "long"})} (${d.toLocaleDateString("ru-RU", {weekday: "long"})})`;
						date.style.fontSize = "32px";
						date.style.fontWeight = "900";
						date.style.border = "2px solid black";
						widetds.push(date);
						daterow.appendChild(date);

						let groupc = 0;

						for (let [i, rdata] of areainfo.rows.entries())
						{
							if (i > 0)
							{
								spacerow = doc.createElement("tr");
								tab.appendChild(spacerow);

								spacetd = doc.createElement("td")
								spacetd.style.borderLeft = spacetd.style.borderRight = spacetd.style.borderBottom = "2px solid black";
								spacetd.style.height = "20px";
								widetds.push(spacetd);
								spacerow.appendChild(spacetd);
							}

							let header = doc.createElement("tr");
							tab.appendChild(header);

							let groupbells = doc.createElement("td");
							groupbells.innerText = "Группы / Звонки";
							groupbells.style.borderLeft = groupbells.style.borderRight = groupbells.style.borderBottom = "2px solid black";
							groupbells.colSpan = 2;
							header.appendChild(groupbells);

							groupc = rdata.groups.length;

							for (let [g, gname] of rdata.groups.entries())
							{
								let group = doc.createElement("th");
								group.innerText = gname;
								group.style.fontWeight = "900";
								group.style.borderBottom = "2px solid black";
								header.appendChild(group);

								let rooms = doc.createElement("td");
								rooms.innerText = "каб";
								rooms.style.borderLeft = "1px solid black";
								rooms.style.borderRight = g < rdata.groups.length - 1 ? rooms.style.borderLeft : "2px solid black";
								rooms.style.borderBottom = "2px solid black";
								header.appendChild(rooms);
							}

							let lastbells = null;
							let lasthours = {};

							for (let [p, pair] of rdata.pairs.entries())
							{
								let pnum = doc.createElement("th");
								pnum.innerText = pair.num;
								pnum.style.fontWeight = "900";
								pnum.style.borderRight = pnum.style.borderLeft = "2px solid black";
								pnum.style.borderBottom = p == rdata.pairs.length - 1 ? "2px solid black" : "1px solid black";
								pnum.rowSpan = 0;
								
								for (let [h, hour] of pair.hours.entries())
								{
									let hrow = doc.createElement("tr");
									tab.appendChild(hrow);

									if (!pnum.parentElement) hrow.appendChild(pnum);
									pnum.rowSpan++;

									if (hour.bells)
									{
										if (lastbells) lastbells.style.borderBottom = "1px solid black";

										let bells = doc.createElement("td");
										bells.innerText = hour.bells;
										bells.style.borderRight = "2px solid black";
										hrow.appendChild(bells);

										lastbells = bells;
									}
									else if (lastbells)
										lastbells.rowSpan++;

									for (let [g, group] of hour.groups.entries())
									{
										if (group)
										{
											if (lasthours[g]) { lasthours[g][0].style.borderBottom = lasthours[g][1].style.borderBottom = "1px solid black"; }

											let occupation = doc.createElement("td");
											occupation.innerText = group.occupation;
											occupation.style.borderRight = "1px solid black";
											occupation.style.borderBottom = "1px solid black";
											hrow.appendChild(occupation);

											let rooms = doc.createElement("td");
											rooms.innerText = group.rooms;
											rooms.style.borderRight = g < hour.groups.length - 1 ? "1px solid black" : "2px solid black";
											rooms.style.borderBottom = "1px solid black";
											hrow.appendChild(rooms);
											
											lasthours[g] = [occupation, rooms];
										}
										else if (lasthours[g])
										{ lasthours[g][0].rowSpan++; lasthours[g][1].rowSpan++; }
									}
								}

								if (lastbells)
									lastbells.style.borderBottom = "2px solid black";

								for (let [g, occ] of Object.entries(lasthours))
									occ[0].style.borderBottom = occ[1].style.borderBottom = "2px solid black";
							}
						}

						for (let td of widetds)
							td.colSpan = 2 + groupc * 2;
					}
				}
			}, function (status, code, message) {
				doc.body.innerText = `Не удалось обработать конечное расписание (${message})`;
			}, function (status, message) {
				doc.body.innerText = `Не удалось обработать конечное расписание (Ошибка HTTP ${status}: ${message})`;
			});
		});

		let gschedule = dialog.AddOption("Расписание для групп", function(event) { body.LectSchedule = false; body.src = "about:blank"; });
		dialog.AddOption("Расписание для преподавателей", function(event) { body.LectSchedule = true; body.src = "about:blank"; });
		
		dialog.Center();

		gschedule.click();
	}

	static AutoGenerate(from, to)
	{
		Schedules.AutoGenLast = to;
		Schedules.AutoGenActive = true;

		Schedules.elements.date.value = Shared.DateToSql(from);
		Schedules.elements.update.click();
	}

	static AutoGenerateStop()
	{
		Schedules.AutoGenActive = false;
	}

	static AutoGenerateApproach()
	{
		let date = Shared.DateFromSql(Schedules.elements.date.value);
		if (date >= Schedules.AutoGenLast)
		{
			Schedules.AutoGenerateStop();
			return;
		}

		date.setDate(date.getDate() + 1);

		Schedules.elements.date.value = Shared.DateToSql(date);
		Schedules.elements.update.click();
	}

	static Initialize()
	{
		Schedules.bells.id = "editor-info-bells";
		Schedules.bells.SetRequired(true);

		Schedules.elements.bells.after(Schedules.bells);

		Schedules.elements.schedule.addEventListener("change", function(event) {
			Schedules.saved = false;
		});

		Schedules.elements.editorinfo.addEventListener("change", function(event) {
			Schedules.saved = false;
		});

		Schedules.elements.date.addEventListener("change", function(event) {
			let date = Schedules.validateDate();
			if (date) Shared.SetCookie("editor-schedule-date", date);
		});

		Schedules.elements.add?.addEventListener("click", function(event) {
			let groups = [];

			for (let group of Schedules.Groups)
				groups.push(group.Group.id);

			Shared.SelectForeignKey("Groups", function(row) {
				if (!row) return;

				let id = row.Cells.ID.Input.GetDataValue();
				for (let group of Schedules.Groups)
					if (group.Group.id == id)
					{
						Shared.DisplayMessage("Данная группа уже есть в расписании", "error");
						return;
					}

				Schedules.AddGroup({group: {id: id, name: row.Name}});
			}, null, [2, {groups: groups, date: Schedules.validateDate()}]);
		});

		Schedules.elements.clear?.addEventListener("click", function(event) {
			if (!Schedules.saved && !Shared.UnsavedConfirm()) return;

			Schedules.elements.schedule.innerHTML = "";
			Schedules.Groups.splice(0);
		});

		Schedules.elements.open.addEventListener("click", function(event) {
			if (!Schedules.saved && !Shared.UnsavedConfirm()) return;

			let dialog = Shared.CreateDialog("Тип открытия");

			dialog.AddOption("Открыть из списка", function() {
				dialog.remove();

				Shared.QuerySelectList("Выберите год расписаний занятий", "schedule", {actdate: Header.GetActualDate()}, function(value, item) {
					item.Year = Shared.DateFromSql(value).getFullYear();
					item.innerText = `Расписания занятий на ${item.Year} год`;
				}, function(value, item) {
					Shared.QuerySelectList("Выберите месяц расписаний занятий", "schedule", {actdate: Header.GetActualDate(), year: item.Year}, function(value2, item2) {
						item2.Month = Shared.DateFromSql(value2).getMonth();
						item2.innerText = `Расписания занятий на ${Shared.DateFromSql(value2).toLocaleDateString("ru-RU", {month: "long"})}`;
					}, function(value2, item2) {
						Shared.QuerySelectList("Выберите расписание занятий", "schedule", {actdate: Header.GetActualDate(), year: item.Year, month: item2.Month}, function(value3, item3) {
							item3.innerText = `Расписание занятий на ${Shared.DateFromSql(value3).toLocaleDateString("ru-RU", {weekday: "short", year: "numeric", month: "long", day: "numeric"})}`;
						}, function(value3, item3) {
							Schedules.OpenSchedule(value3);
						});
					});
				});
			});
			dialog.AddOption("Открыть по дате", function() {
				dialog.remove();

				let date = Schedules.validateDate();
				if (date) Schedules.OpenSchedule(date);
			});

			dialog.SetWidth(500);
			dialog.SetHeight(0);
			dialog.Center();
		});

		Schedules.elements.view.addEventListener("click", function(event) {
			if (Schedules.elements.schedule.classList.contains("schedules-count2"))
				Schedules.elements.schedule.classList.remove("schedules-count2");
			else
				Schedules.elements.schedule.classList.add("schedules-count2");

			Shared.SetCookie("editor-schedules-count2", Schedules.elements.schedule.classList.contains("schedules-count2") ? 1 : 0);
		});

		Schedules.elements.export.addEventListener("click", function(event) {
			let schedule = Schedules.GetValidatedSchedule();
			if (!schedule) return;

			Schedules.ExportSchedule(schedule);
		});

		Schedules.elements.update?.addEventListener("click", function(event) {
			if (!Shared.AutoGenActive && !Schedules.saved && !Shared.UnsavedConfirm()) return;

			let lock = Schedules.GetValidatedSchedule(true);
			if (!lock) return;

			Schedules.lastmsg?.remove();
			let info = Shared.DisplayMessage(`Формируется расписание занятий (${Schedules.DateString(lock.date)})...`, "warning");

			Shared.RequestPOST("schedule", lock.groups.length == 0 ? {} : lock, {
				type: "construct",
				bells: lock.bells.id,
				date: lock.date,
				actdate: Header.GetActualDate(),
			}, function(data) {
				Schedules.LoadSchedule(data);
				Schedules.saved = true;

				Schedules.lastmsg?.remove();
				Schedules.lastmsg = Shared.DisplayMessage(`Сформировано расписание занятий (${Schedules.DateString(data.date)})`, "success");

				if (Schedules.AutoGenActive)
					if (Schedules.Groups.length == 0)
						Schedules.AutoGenerateApproach();
					else
						Schedules.elements.save.click();
			}, function (status, code, message) {
				Schedules.lastmsg?.remove();
				Schedules.lastmsg = Shared.DisplayMessage(`Не удалось сформировать расписание занятий (${Schedules.DateString(lock.date)}) (${message})`, "error");
			}, function (status, message) {
				Schedules.lastmsg?.remove();
				Schedules.lastmsg = Shared.DisplayMessage(`Не удалось сформировать расписание занятий (${Schedules.DateString(lock.date)}) (Ошибка HTTP ${status}: ${message})`, "error");
			}, function (status, message) {
				info.remove();
			});
		});

		Schedules.elements.configure?.addEventListener("click", function(event) {
			let dialog = Shared.CreateDialog("Конфигурация влияния критериев приоритизации конструктора расписаний");

			let placeholder = document.createElement("div");
			placeholder.innerText = "Получение списка критериев приоритизации...";
			placeholder.className = "modaldialog-placeholder";
			dialog.Body.appendChild(placeholder);

			Shared.RequestGET("algorithmmodifiers", {
				type: "schedule",
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
						let cookiename = `editor-schedule-config-${internal}-${id}`;

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

				LoadCategory("main", "Основной алгоритм выбора группы");
				LoadCategory("occupation", "Алгоритм выбора предмета");
				LoadCategory("hournum", "Алгоритм выбора номера часа");
			}, function(status, code, message) {
				placeholder.innerText = `Не удалось получить список критериев (${message})`;
			}, function (status, message) {
				placeholder.innerText = `Не удалось получить список критериев (Ошибка HTTP ${status}: ${message})`;
			});

			dialog.Center();
		});

		Schedules.elements.validate?.addEventListener("click", function(event) {
			let schedule = Schedules.GetValidatedSchedule();
			if (!schedule) return;

			Schedules.lastmsg?.remove();
			let info = Shared.DisplayMessage(`Идёт проверка расписания занятий (${Schedules.DateString(schedule.date)})...`, "warning");

			Shared.RequestPOST("schedule", schedule, {
				type: "check",
				actdate: Header.GetActualDate(),
			}, function(data) {
				Schedules.lastmsg?.remove();
				Schedules.lastmsg = Shared.DisplayMessage(`Расписание занятий (${Schedules.DateString(schedule.date)}) успешно прошло проверку`, "success");
			}, function (status, code, message) {
				Schedules.lastmsg?.remove();

				if (code >= 3)
					Schedules.lastmsg = Shared.DisplayMessage(`Расписание занятий (${Schedules.DateString(schedule.date)}) не прошло проверку (${message})`, "error");
				else
					Schedules.lastmsg = Shared.DisplayMessage(`Не удалось проверить расписание занятий (${Schedules.DateString(schedule.date)}) (${message})`, "error");
			}, function (status, message) {
				Schedules.lastmsg?.remove();
				Schedules.lastmsg = Shared.DisplayMessage(`Не удалось проверить расписание занятий (${Schedules.DateString(schedule.date)}) (Ошибка HTTP ${status}: ${message})`, "error");
			}, function (status, message) {
				info.remove();
			});
		});

		Schedules.elements.save?.addEventListener("click", function(event) {
			let schedule = Schedules.GetValidatedSchedule();
			if (!schedule) return;
			
			if (!Schedules.AutoGenActive && !confirm(`Сохранить расписание занятий (${Schedules.DateString(schedule.date)})?`)) return;

			Schedules.lastmsg?.remove();
			let info = Shared.DisplayMessage(`Идёт сохранение расписания занятий (${Schedules.DateString(schedule.date)})...`, "warning");

			Shared.RequestPOST("schedule", schedule, {
				type: "save",
				actdate: Header.GetActualDate(),
			}, function(data) {
				Schedules.lastmsg?.remove();
				Schedules.lastmsg = Shared.DisplayMessage(`Расписание занятий (${Schedules.DateString(schedule.date)}) успешно сохранено`, "success");

				if (Schedules.AutoGenActive)
					Schedules.AutoGenerateApproach();
			}, function (status, code, message) {
				Schedules.lastmsg?.remove();

				if (code >= 3)
					Schedules.lastmsg = Shared.DisplayMessage(`Расписание занятий (${Schedules.DateString(schedule.date)}) не прошло проверку (${message})`, "error");
				else
					Schedules.lastmsg = Shared.DisplayMessage(`Не удалось сохранить расписание занятий (${Schedules.DateString(schedule.date)}) (${message})`, "error");
			}, function (status, message) {
				Schedules.lastmsg?.remove();
				Schedules.lastmsg = Shared.DisplayMessage(`Не удалось сохранить расписание занятий (${Schedules.DateString(schedule.date)}) (Ошибка HTTP ${status}: ${message})`, "error");
			}, function (status, message) {
				info.remove();
			});
		});

		Schedules.elements.delete?.addEventListener("click", function(event) {
			let date = Schedules.validateDate();
			if (!date || !confirm(`Удалить расписание занятий (${Schedules.DateString(date)})?`)) return;

			Schedules.lastmsg?.remove();
			let info = Shared.DisplayMessage(`Идёт удаление расписания занятий (${Schedules.DateString(date)})...`, "warning");

			Shared.RequestPOST("schedule", {}, {
				type: "delete",
				date: date,
				actdate: Header.GetActualDate(),
			}, function(data) {
				Schedules.lastmsg?.remove();
				Schedules.lastmsg = Shared.DisplayMessage(`Расписание занятий (${Schedules.DateString(date)}) успешно удалено`, "success");
			}, function (status, code, message) {
				Schedules.lastmsg?.remove();
				Schedules.lastmsg = Shared.DisplayMessage(`Не удалось удалить расписание занятий (${Schedules.DateString(date)}) (${message})`, "error");
			}, function (status, message) {
				Schedules.lastmsg?.remove();
				Schedules.lastmsg = Shared.DisplayMessage(`Не удалось удалить расписание занятий (${Schedules.DateString(date)}) (Ошибка HTTP ${status}: ${message})`, "error");
			}, function (status, message) {
				info.remove();
			});
		});

		onbeforeunload = function(event)
		{
			if (!Schedules.saved)
				return "Расписание не сохранено";
		}

		if (Shared.GetCookie("editor-schedule-date"))
			Schedules.elements.date.value = Shared.GetCookie("editor-schedule-date");

		if (Shared.GetCookie("editor-schedules-count2"))
			Schedules.elements.schedule.classList.add("schedules-count2");

		let date = Schedules.validateDate();
		if (date) Schedules.OpenSchedule(date);

		if (Schedules.elements.save)
			window.addEventListener("keydown", function(event) {
				if (!event.ctrlKey || event.code != "KeyS") return;

				event.preventDefault();

				Schedules.elements.save.click();
			});
	}
}
Schedules.Initialize();