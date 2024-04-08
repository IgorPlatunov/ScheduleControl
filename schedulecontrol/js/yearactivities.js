class YearActivities
{
	table = null;
	thead = null;
	tbody = null;

	year = 2000;
	customcolumns = [];
	weeks = [];
	rows = [];

	static monthnames = ["Январь", "Февраль", "Март", "Апрель", "Май", "Июнь", "Июль", "Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь"];
	static daysnames = ["ПН", "ВТ", "СР", "ЧТ", "ПТ", "СБ", "ВС"];
	static daysdisplay = [true, false, true, false, false, true, false];

	startdate = null;
	enddate = null;
	startweek = 0;
	endweek = 0;

	constructor()
	{
		this.table = document.createElement("table");
		this.table.className = "year-activities-table";

		let rows = this.rows;
		new ResizeObserver(function() {
			for (let row of rows)
				row.Update();
		}).observe(this.table);
	}

	SetParent(element) { element.appendChild(this.table); }

	Remove() { this.table.remove(); }

	AddCustomColumn(name) { this.customcolumns.push(name); }

	SetYear(year)
	{
		this.year = year;

		this.startdate = new Date(year, 8, 1);
		this.enddate = new Date(year + 1, 8, 1);

		let swday = YearActivities.GetWDay(this.startdate);
		if (swday > 4) this.startdate.setDate(this.startdate.getDate() - swday + 7);

		this.startweek = YearActivities.GetWDay(this.startdate) / 7;
		this.endweek = this.DateToWeek(this.enddate);
	}

	SetupTable()
	{
		this.table.innerHTML = "";

		let customcols = document.createElement("colgroup");
		this.table.appendChild(customcols);

		let headercols = document.createElement("colgroup");
		this.table.appendChild(customcols);

		let main = document.createElement("colgroup");
		this.table.appendChild(customcols);

		this.thead = this.table.createTHead();
		this.tbody = this.table.createTBody();

		let days = [];

		for (let d in YearActivities.daysdisplay)
		{
			days[d] = document.createElement("tr");
			days[d].hidden = !YearActivities.daysdisplay[d];

			this.thead.appendChild(days[d]);
		}

		let month = document.createElement("tr");
		this.thead.appendChild(month);

		let week = document.createElement("tr");
		this.thead.appendChild(week);

		let custom = []

		for (let c in this.customcolumns)
		{
			customcols.appendChild(document.createElement("col"));
			
			custom[c] = document.createElement("th");
			custom[c].scope = "col";
			custom[c].rowSpan = 2;
			custom[c].innerText = this.customcolumns[c];
			days[0].appendChild(custom[c]);
		}

		let headercol = document.createElement("col");
		headercol.span = 2;
		headercols.appendChild(headercol);

		let weekdays = document.createElement("th");
		weekdays.scope = "row";
		weekdays.rowSpan = 0;
		weekdays.innerText = "Дни недели";
		days[0].appendChild(weekdays);

		for (let d in days)
		{
			if (!days[d].hidden)
			{
				weekdays.rowSpan++;

				for (let c of custom)
					c.rowSpan++;
			}

			let day = document.createElement("th");
			day.scope = "row";
			day.innerText = YearActivities.daysnames[d];
			days[d].appendChild(day);
		}

		let months = document.createElement("th");
		months.scope = "row";
		months.innerText = "Месяц";
		months.colSpan = 2;
		month.appendChild(months);

		let weeks = document.createElement("th");
		weeks.scope = "row";
		weeks.innerText = "Номер недели";
		weeks.colSpan = 2;
		week.appendChild(weeks);

		let date = new Date(this.startdate);
		date.setDate(date.getDate() - YearActivities.GetWDay(date));

		let cweek = 0, cmonth = null;

		this.weeks.splice(0);

		do
		{
			let wday = YearActivities.GetWDay(date);

			let day = document.createElement("th");
			days[wday].appendChild(day);

			if (date.getTime() >= this.startdate.getTime() && date.getTime() < this.enddate.getTime())
				day.innerText = date.getDate();

			if (date.getTime() >= this.startdate.getTime() && (wday == 0 || cweek == 0))
			{
				cweek++;

				let w = document.createElement("th");
				w.innerText = cweek;
				week.appendChild(w);

				this.weeks.push(w);

				if (!cmonth || cmonth.Month != date.getMonth())
				{
					cmonth = document.createElement("th");
					cmonth.innerText = YearActivities.monthnames[date.getMonth()];
					cmonth.Month = date.getMonth();
					month.appendChild(cmonth);
				}
				else
					cmonth.colSpan++;
			}

			date.setDate(date.getDate() + 1);
		} while(date.getTime() < this.enddate.getTime() || YearActivities.GetWDay(date) != 0);

		main.span = cweek;

		for (let row of this.rows)
		{
			this.tbody.appendChild(row);
			row.Main.colSpan = cweek;

			for (let i = row.Activities.length - 1; i >= 0; i--)
			{
				let act = row.Activities[i];

				if (
					act.Week <= this.startweek && act.Week + act.Length <= this.startweek ||
					act.Week >= this.endweek && act.Week + act.Length >= this.endweek
				)
					act.Remove();

				if (act.Week < this.startweek)
					act.Week = this.startweek;
					
				if (act.Week + act.Length > this.endweek)
					act.Length = this.endweek - act.Week;

				if (i == 0)
				{
					act.Length -= this.startweek - act.Week;
					act.Week = this.startweek;
				}
				else if (i == row.Activities.length - 1)
					act.Length = this.endweek - act.Week;
			}
		}

		for (let row of this.rows)
			row.Update();

		Shared.BubbleEvent(this.tbody, "change");
	}

	AddRow(custom, empty)
	{
		let row = document.createElement("tr");
		row.Custom = [];
		row.Activities = [];
		row.Movers = [];
		row.CustomYear = null;

		this.tbody.appendChild(row);
		this.rows.push(row);

		for (let c in this.customcolumns)
		{
			let td = document.createElement("td");
			row.appendChild(td);

			if (custom[c])
			{
				td.append(custom[c]);
				row.Custom[c] = custom[c];
			}

			if (c == this.customcolumns.length - 1)
				td.colSpan = 3;
		}

		let main = document.createElement("td");
		main.style.position = "relative";
		main.colSpan = this.weeks.length;
		row.appendChild(main);

		row.Main = main;

		let graph = this;
		row.CreateActivity = function()
		{
			let act = document.createElement("div");
			act.className = "year-activities-act";
			act.style.position = "absolute";
			act.style.top = "0";
			act.style.height = "100%";
			act.Week = graph.startweek;
			act.Length = graph.endweek - act.Week;
			act.Semester2 = false;
			main.appendChild(act);

			act.UpdateText = function() {
				act.innerText = act.ActivityName ?? "-";

				let start = graph.WeekToDate(act.Week);
				let end = graph.WeekToDate(act.Week + act.Length);
				end.setDate(end.getDate() - 1);

				if (row.CustomYear)
				{
					start.setFullYear(start.getFullYear() + row.CustomYear - graph.startdate.getFullYear());
					end.setFullYear(end.getFullYear() + row.CustomYear - graph.startdate.getFullYear());
				}

				let sum = 0;

				for (let activity of row.Activities)
					if (activity.Activity == act.Activity && activity.Semester2 == act.Semester2)
						sum += activity.Length;

				act.title =
`Деятельность: ${act.ActivityFullName}
Период: ${YearActivities.DateToString(start)} - ${YearActivities.DateToString(end)}
Длительность отрезка: ${Math.round(act.Length * 7)} дней (${Math.floor(act.Length)} недель + ${Math.round(act.Length % 1 * 7)} дней)
Длительность всех отрезков семестра: ${Math.round(sum * 7)} дней (${Math.floor(sum)} недель + ${Math.round(sum % 1 * 7)} дней)`;
			}

			act.UpdatePosition = function() {
				let pos = graph.WeekToCoord(act.Week);
				let width = graph.WeekToCoord(act.Week + act.Length) - pos;

				act.style.left = `${pos}px`;
				act.style.width = `${width}px`;
			}

			act.SetActivity = function(activity, name, fullname) {
				act.Activity = activity ?? null;
				act.ActivityName = name ?? "-";
				act.ActivityFullName = fullname ?? "Нет деятельности";
			}
			act.SetActivity();

			act.Remove = function()
			{
				act.remove();
				let index = Shared.RemoveFromArray(row.Activities, act);

				if (index == 0)
				{
					row.Activities[index].Week = act.Week;
					row.Activities[index].Length += act.Length;
				}
				else
					row.Activities[index - 1].Length += act.Length;
			}

			let menux = null;

			act.Menu = Shared.ContextMenu(act, [
				{type: "button", text: "Выбрать деятельность...", onclick: function(event) {
					Shared.SelectForeignKey("Activities", function(datarow) {
						act.SetActivity(datarow?.Cells.ID.Input.GetDataValue(), datarow?.Name, datarow?.FullName);
						row.UpdateActivities();

						Shared.BubbleEvent(act, "change");
					});
				}},
				{type: "button", text: "Разбить на 2 деятельности здесь", onclick: function(event) {
					let week = graph.CoordToWeek(menux);

					let nact = row.CreateActivity();
					nact.SetActivity(act.Activity, act.ActivityName);
					nact.Set2Semester(act.Semester2);
					nact.Week = week;
					nact.Length = act.Week + act.Length - week;

					act.Length = week - act.Week;

					row.UpdateActivities();
					row.UpdateMovers();

					Shared.BubbleEvent(act, "change");
				}},
				{type: "checkbox", text: "Второй семестр", onchange: function(semester2) {
					act.Set2Semester(semester2);
				}},
				{type: "button", text: "Удалить", onclick: function(event) {
					if (row.Activities.length <= 1) return;

					act.Remove();

					row.UpdateActivities();
					row.UpdateMovers();

					Shared.BubbleEvent(row, "change");
				}},
			], function(event) {
				menux = event.offsetX + event.target.offsetLeft;

				let allowdiv = true;

				let week = Math.round(graph.CoordToWeek(menux) * 7) / 7;
				let date = graph.WeekToDate(week);

				if (
					date.getTime() <= graph.WeekToDate(act.Week).getTime() ||
					date.getTime() >= graph.WeekToDate(act.Week + act.Length).getTime()
				) allowdiv = false;

				act.Menu.Items[1].innerText = `Разбить на 2 деятельности здесь (${YearActivities.DateToString(date)})`;
				act.Menu.Items[1].disabled = !allowdiv;
			});

			act.Set2Semester = function(semester2)
			{
				act.Semester2 = semester2;
				act.Menu.Items[2].SetChecked(semester2);

				if (semester2) act.classList.add("year-activities-act-2-semester");
				else act.classList.remove("year-activities-act-2-semester");
			}

			row.Activities.push(act);

			Shared.BubbleEvent(row, "change");

			return act;
		}

		row.CreateMover = function(leftact, rightact)
		{
			let mover = document.createElement("div");
			mover.className = "year-activities-mover";
			mover.style.position = "absolute";
			mover.style.top = "0";
			mover.style.width = "2px";
			mover.style.height = "100%";
			main.appendChild(mover);

			let helper = document.createElement("div");
			helper.className = "year-activities-helper";
			mover.appendChild(helper);

			mover.Helper = helper;

			let dlen = 1 / 7;
			mover.UpdateWeek = function(week)
			{
				week = Math.round(week * 7) / 7;

				if (week < leftact.Week + dlen) week = leftact.Week + dlen;
				if (week > rightact.Week + rightact.Length - dlen)
					week = rightact.Week + rightact.Length - dlen;

				mover.Week = week;
				mover.style.left = `${graph.WeekToCoord(week) - 1}px`;

				leftact.Length = week - leftact.Week;
				rightact.Length += rightact.Week - week;
				rightact.Week = week;

				helper.UpdateText();
			}
			
			helper.UpdateText = function()
			{
				let date = graph.WeekToDate(mover.Week);

				if (row.CustomYear)
					date.setFullYear(date.getFullYear() + row.CustomYear - graph.startdate.getFullYear());

				helper.innerText = YearActivities.DateToString(date);
			}

			mover.UpdateWeek(rightact.Week);

			mover.addEventListener("mousedown", function(event) {
				if (event.button != 0) return;
				event.preventDefault();

				mover.classList.add("year-activities-moving");

				let sleft = mover.offsetLeft + 1;
				let move, up;

				move = function(event2) {
					let week = graph.CoordToWeek(sleft + event2.pageX - event.pageX);
					mover.UpdateWeek(week);
					row.UpdateActivities();

					Shared.BubbleEvent(leftact, "change");
					Shared.BubbleEvent(rightact, "change");
				}

				up = function(event2) {
					if (event2.button != 0) return;
					event2.preventDefault();

					mover.classList.remove("year-activities-moving");

					document.body.removeEventListener("mousemove", move);
					document.body.removeEventListener("mouseup", up);

					row.UpdateActivities();
				}

				document.body.addEventListener("mousemove", move);
				document.body.addEventListener("mouseup", up);
			});

			row.Movers.push(mover);
		}

		row.Clear = function()
		{
			for (let act of row.Activities)
				act.remove();

			row.Activities.splice(0);
		}

		row.UpdateActivities = function()
		{
			row.Activities.sort(function(a, b) { return a.Week < b.Week ? -1 : a.Week > b.Week ? 1 : 0; });

			for (let act of row.Activities)
			{
				act.UpdatePosition();
				act.UpdateText();

				act.Menu.Items[3].disabled = row.Activities.length <= 1;
			}
		}

		row.UpdateMovers = function()
		{
			for (let mover of row.Movers)
				mover.remove();

			row.Movers.splice(0);

			for (let [id, act] of row.Activities.entries())
				if (id > 0) row.CreateMover(row.Activities[id - 1], act);
		}

		row.Update = function()
		{
			row.UpdateActivities();
			row.UpdateMovers();
		}

		if (!empty)
		{
			row.CreateActivity();
			row.UpdateActivities();
		}

		Shared.BubbleEvent(this.tbody, "change");
		
		return row;
	}

	ClearRows()
	{
		for (let row of this.rows)
			row.remove();

		this.rows.splice(0);
	}

	GetWeekStartCoord(week)
	{
		return this.weeks[week].offsetLeft - this.weeks[0].offsetLeft;
	}

	GetWeekEndCoord(week)
	{
		if (week < this.weeks.length - 1)
			return this.GetWeekStartCoord(week + 1);
		
		return this.GetWeekStartCoord(week) + this.weeks[week].offsetWidth;
	}

	WeekToCoord(week)
	{
		let cweek = Math.floor(week);
		if (cweek < 0) return 0;

		if (cweek > this.weeks.length - 1)
			return this.GetWeekEndCoord(this.weeks.length - 1);

		let frs = 0, fre = 1;

		if (week < 1) frs = this.startweek;
		else if (week >= Math.floor(this.endweek)) fre = this.endweek % 1;

		let start = this.GetWeekStartCoord(cweek);
		let end = this.GetWeekEndCoord(cweek);
		let mod = week - cweek;

		return Shared.Remap(mod, frs, fre, start, end);
	}

	CoordToWeek(coord)
	{
		for (let wkn in this.weeks)
		{
			let weekn = parseInt(wkn);
			let start = this.GetWeekStartCoord(weekn);
			let end = this.GetWeekEndCoord(weekn);

			if (coord >= start && coord < end)
			{
				let frs = 0, fre = 1;

				if (weekn == 0) frs = this.startweek;
				else if (weekn == this.weeks.length - 1) fre = this.endweek % 1;

				return weekn + Shared.Remap(coord, start, end, frs, fre);
			}
		}

		if (coord < 0) return this.startweek;
		else return this.endweek;
	}

	WeekToDate(week)
	{
		let date = new Date(this.startdate);
		date.setTime(date.getTime() + Math.round((week - this.startweek) * 7) * 24 * 60 * 60 * 1000);

		return date;
	}

	DateToWeek(date)
	{
		let diff = (date.getTime() - this.startdate.getTime()) / 1000 / 60 / 60 / 24 / 7;
		return this.startweek + diff;
	}

	GetData()
	{
		let rows = [];

		for (let row of this.rows)
		{
			let activities = [];

			for (let act of row.Activities)
				if (act.Activity)
					activities.push({
						activity: act.Activity ? {id: act.Activity, name: act.ActivityName, fullname: act.ActivityFullName} : null,
						week: act.Week,
						length: act.Length,
						semester: act.Semester2 ? 2 : 1,
					});

			rows.push(activities);
		}

		return rows;
	}

	LoadData(data, custom)
	{
		this.SetupTable();

		for (let [rid, rdata] of data.entries())
		{
			let row = this.AddRow(custom[rid] ?? [], true);

			for (let [aid, adata] of rdata.entries())
			{
				let act = row.CreateActivity();
				act.Week = Math.max(adata.week, this.startweek);
				act.Length = Math.min(adata.week + adata.length, this.endweek) - act.Week;
				act.SetActivity(adata.activity?.id, adata.activity?.name, adata.activity?.fullname);
				act.Set2Semester(adata.semester == 2);
			}

			for (let i = row.Activities.length; i >= 0; i--)
			{
				let lact = row.Activities[i - 1] ? row.Activities[i - 1].Week + row.Activities[i - 1].Length : this.startweek;
				let ract = row.Activities[i]?.Week ?? this.endweek;

				if (ract - lact >= 1 / 7)
				{
					let act = row.CreateActivity();
					act.Week = lact;
					act.Length = ract - lact;
					act.Set2Semester((row.Activities[i - 1] ?? row.Activities[i])?.Semester2 ?? false);
				}
			}

			row.Update();
		}
	}

	static GetWDay(date)
	{
		let wday = date.getDay() - 1;
		return wday < 0 ? 6 : wday;
	}

	static DateToString(date)
	{
		return date.toLocaleDateString("ru-RU", {weekday: "short", year: "numeric", month: "long", day: "numeric"})
	}
}