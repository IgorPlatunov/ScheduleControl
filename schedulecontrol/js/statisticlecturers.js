class StatisticLecturers
{
	static elements = Shared.MapElements({
		date: "#group-date",
		lecturer: "#lecturer-container",
		load: "#lecturer-load",
		subjects: "#subjects-info",
		update: "#update-btn",
		export: "#export-btn",
		collapse: "#lecturer-load-collapse",
	});

	static sortbtns = Shared.MapVarElements(".subject-sort-btn", "id");
	static filters = Shared.MapVarElements(".subject-filter", "id");
	static columns = [];
	static sumrow = null;
	static sort = [];
	static subjects = [];

	static lecturer = null;
	static lastmsg = null;

	static validateDate()
	{
		if (!StatisticLecturers.elements.date.reportValidity())
			return false;

		if (StatisticLecturers.elements.date.value == "")
		{
			Shared.DisplayMessage("Некорректный формат даты", "error");
			return false;
		}

		return Shared.DateToSql(new Date(StatisticLecturers.elements.date.value), false);
	}

	static SetupInfo(info)
	{
		StatisticLecturers.elements.load.tBodies[0].innerHTML = "";
		StatisticLecturers.elements.subjects.tBodies[0].innerHTML = "";
		
		if (info)
		{
			for (let subject of info.load.subjects)
			{
				let name = document.createElement("td");
				name.innerText = subject.subject.fullname;
				name.NameTD = true;
				name.rowSpan = 0;

				for (let group of subject.groups)
				{
					let row = document.createElement("tr");
					StatisticLecturers.elements.load.tBodies[0].appendChild(row);

					if (!name.parentElement)
					{
						row.classList.add("subject-name-row");
						row.appendChild(name);
					}
					name.rowSpan++;

					let gname = document.createElement("td");
					gname.innerText = group.group.name;
					row.appendChild(gname);

					let chours = document.createElement("td");
					row.appendChild(chours);

					let hours = document.createElement("td");
					row.appendChild(hours);

					let choursn = 0;
					let hoursn = 0;

					for (let s = 1; s <= 2; s++)
					{
						let semester = group.semesters[s];

						choursn += semester?.chours ?? 0;
						hoursn += semester?.hours ?? 0;

						let diff = document.createElement("td");
						diff.innerText = semester ? -semester.diff : "";
						row.appendChild(diff);
					}

					chours.innerText = choursn;
					hours.innerText = hoursn;

					for (let s = 1; s <= 2; s++)
					{
						let semester = group.semesters[s];

						let hour = document.createElement("td");
						hour.innerText = semester?.hours ?? "";
						row.appendChild(hour);

						let whours = document.createElement("td");
						whours.innerText = semester?.whours ?? "";
						row.appendChild(whours);

						let lphours = document.createElement("td");
						lphours.innerText = semester?.lphours ?? "";
						row.appendChild(lphours);

						let cdhours = document.createElement("td");
						cdhours.innerText = semester?.cdhours ?? "";
						row.appendChild(cdhours);

						let cdphours = document.createElement("td");
						cdphours.innerText = semester?.cdphours ?? "";
						row.appendChild(cdphours);

						let ehours = document.createElement("td");
						ehours.innerText = semester?.ehours ?? "";
						row.appendChild(ehours);
					}
				}
			}

			StatisticLecturers.subjects = info.subjects;
			StatisticLecturers.sumrow = StatisticLecturers.subjects[StatisticLecturers.subjects.length - 1];

			for (let subject of StatisticLecturers.subjects)
			{
				subject.sum = {};

				for (let column of StatisticLecturers.columns)
					if (!column.sort.dataset.subject)
					{
						let sum = 0;

						for (let group of subject.groups)
						{
							let value = parseFloat(group[column.sort.dataset.value]);
							if (!isNaN(value)) sum += value;
						}

						subject.sum[column.sort.dataset.value] = sum;
					}
			}

			StatisticLecturers.UpdateSubjects();
		}
	}

	static OpenInfo()
	{
		let lecturer = StatisticLecturers.lecturer.Value;
		let name = StatisticLecturers.lecturer.Name;

		StatisticLecturers.lastmsg?.remove();
		let info = Shared.DisplayMessage(`Загружается статистика преподавателя ${name}...`, "warning");

		Shared.RequestGET("lecturerstatistic", {
			date: StatisticLecturers.validateDate(),
			actdate: Header.GetActualDate(),
			lecturer: lecturer,
		}, function(data) {
			StatisticLecturers.SetupInfo(data);

			StatisticLecturers.lastmsg?.remove();
			StatisticLecturers.lastmsg = Shared.DisplayMessage(`Загружена статистика преподавателя ${name}`, "success");
		}, function (status, code, message) {
			StatisticLecturers.lastmsg?.remove();
			StatisticLecturers.lastmsg = Shared.DisplayMessage(`Не удалось загрузить статистику преподавателя ${name} (${message})`, "error");
		}, function (status, message) {
			StatisticLecturers.lastmsg?.remove();
			StatisticLecturers.lastmsg = Shared.DisplayMessage(`Не удалось загрузить статистику преподавателя ${name} (Ошибка HTTP ${status}: ${message})`, "error");
		}, function (status, message) {
			info.remove();
		});
	}

	static UpdateSubjects()
	{
		StatisticLecturers.elements.subjects.tBodies[0].innerHTML = "";

		let filters = {};
		for (let [id, column] of StatisticLecturers.columns.entries())
			if (column.filter)
				filters[id] = column.filter.value.toLowerCase();

		StatisticLecturers.subjects.sort(function(a, b) {
			if (a == StatisticLecturers.sumrow || b == StatisticLecturers.sumrow)
				return a == StatisticLecturers.sumrow ? 1 : -1;

			for (let column of StatisticLecturers.sort)
			{
				let col = StatisticLecturers.columns[column].sort;

				let aval = col.dataset.subject ? a[col.dataset.value] : a.sum[col.dataset.value];
				let bval = col.dataset.subject ? b[col.dataset.value] : b.sum[col.dataset.value];

				if (aval == bval) continue;

				let iaval = parseFloat(aval);
				let ibval = parseFloat(bval);

				if (!isNaN(iaval) && !isNaN(ibval))
					return (col.Asc ? iaval < ibval : iaval > ibval) ? -1 : 1;
				else
					return (col.Asc ? aval < bval : aval > bval) ? -1 : 1;
			}

			return 0;
		});

		for (let subject of StatisticLecturers.subjects)
		{
			subject.groups.sort(function(a, b) {
				for (let column of StatisticLecturers.sort)
				{
					let col = StatisticLecturers.columns[column].sort.dataset;
					if (col.subject) continue;

					let aval = a[col.value];
					let bval = b[col.value];

					if (aval == bval) continue;

					let iaval = parseFloat(aval);
					let ibval = parseFloat(bval);

					if (!isNaN(iaval) && !isNaN(ibval))
						return (col.Asc ? iaval < ibval : iaval > ibval) ? -1 : 1;
					else
						return (col.Asc ? aval < bval : aval > bval) ? -1 : 1;
				}

				return 0;
			});

			let subjtds = [];

			for (let group of subject.groups)
			{
				let hide = false;

				for (let [id, column] of StatisticLecturers.columns.entries())
					if (filters[id] != null)
					{
						let found = false;

						for (let val of column.sort.dataset.filter.split(";"))
							if ((column.sort.dataset.subject ? subject[val] : group[val]).toLowerCase().indexOf(filters[id]) >= 0)
								{ found = true; break; }

						if (!found) { hide = true; break; }
					}

				if (!hide)
				{
					let row = document.createElement("tr");
					StatisticLecturers.elements.subjects.tBodies[0].appendChild(row);

					for (let [id, column] of StatisticLecturers.columns.entries())
					{
						if (column.sort.dataset.subject)
						{
							if (!subjtds[id])
							{
								subjtds[id] = document.createElement("td");
								subjtds[id].innerText = column.sort.dataset.format.replace("%s", subject[column.sort.dataset.value]);
								subjtds[id].rowSpan = 0;
								row.appendChild(subjtds[id]);

								row.classList.add("subject-name-row");
							}

							subjtds[id].rowSpan++;
						}
						else
						{
							let td = document.createElement("td");
							td.innerText = column.sort.dataset.format.replace("%s", group[column.sort.dataset.value]);
							row.appendChild(td);
						}
					}
				}
			}
		}
	}

	static ExportStatistic()
	{
		let dialog = Shared.CreateDialog("Предпросмотр и экспорт статистики");
		let lecturer = StatisticLecturers.lecturer.FullName;
		let lectname = StatisticLecturers.lecturer.Name;

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
				Shared.ExportTableToExcel(tab, "Статистика преподавателя", `Статистика преподавателя ${lectname}`);
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

				let headgroup = doc.createElement("th");
				headgroup.innerText = "Группа";
				headgroup.style.borderTop = headgroup.style.borderRight = headgroup.style.borderBottom = "2px solid black";
				headgroup.style.fontWeight = "900";
				headtr.appendChild(headgroup);

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

				let lnametd = null;
				let rows = StatisticLecturers.elements.subjects.tBodies[0].rows;

				for (let row of rows)
				{
					let tr = doc.createElement("tr");
					tab.appendChild(tr);

					let offset = row.classList.contains("subject-name-row") ? 0 : -1;

					for (let cell of row.cells)
					{
						let td = doc.createElement("td");
						td.innerText = cell.innerText;
						td.rowSpan = cell.rowSpan;
						td.style.borderRight = "1px solid black";
						td.style.borderBottom = row == rows[rows.length - 1] ? "2px solid black" : "1px solid black";
						td.style.textAlign = "right";
						tr.appendChild(td);

						if (cell == row.cells[0] && row.classList.contains("subject-name-row"))
						{
							lnametd = td;
							td.style.borderLeft = "2px solid black";
						}

						if (cell == row.cells[5 + offset])
							td.style.borderRight = "2px solid black";

						if (cell == row.cells[1 + offset] || td == lnametd)
						{
							td.style.textAlign = "left";
							td.style.borderRight = "2px solid black";
						}
					}
				}

				if (lnametd) lnametd.style.borderBottom = "2px solid black";
			}
			else
			{
				let htr = doc.createElement("tr");
				tab.appendChild(htr);

				let hname = doc.createElement("th");
				hname.innerText = "ПЕДАГОГИЧЕСКАЯ НАГРУЗКА";
				hname.style.border = "2px solid black";
				hname.style.fontWeight = "900";
				hname.colSpan = 18;
				htr.appendChild(hname);

				let ltr = doc.createElement("tr");
				tab.appendChild(ltr);

				let lname = doc.createElement("th");
				lname.innerText = `Преподаватель: ${lecturer}`;
				lname.style.borderLeft = lname.style.borderRight = lname.style.borderBottom = "2px solid black";
				lname.style.fontWeight = "900";
				lname.style.textAlign = "left";
				lname.colSpan =  18;
				ltr.appendChild(lname);

				let h1tr = doc.createElement("tr");
				tab.appendChild(h1tr);

				let hsubj = doc.createElement("th");
				hsubj.innerText = "Наименование предмета";
				hsubj.rowSpan = 3;
				hsubj.style.borderLeft = hsubj.style.borderRight = hsubj.style.borderBottom = "2px solid black";
				hsubj.style.fontWeight = "900";
				h1tr.appendChild(hsubj);

				let hgroup = doc.createElement("th");
				hgroup.innerText = "Группа";
				hgroup.rowSpan = 3;
				hgroup.style.borderRight = hgroup.style.borderBottom = "2px solid black";
				hgroup.style.fontWeight = "900";
				h1tr.appendChild(hgroup);

				let hdechours = doc.createElement("th");
				hdechours.innerText = "Количество часов";
				hdechours.colSpan = 4;
				hdechours.style.borderRight = hdechours.style.borderBottom = "2px solid black";
				hdechours.style.fontWeight = "900";
				h1tr.appendChild(hdechours);

				let hcrshours = doc.createElement("th");
				hcrshours.innerText = "Часы по нагрузке";
				hcrshours.colSpan = 12;
				hcrshours.style.borderRight = hcrshours.style.borderBottom = "2px solid black";
				hcrshours.style.fontWeight = "900";
				h1tr.appendChild(hcrshours);

				let h2tr = doc.createElement("tr");
				tab.appendChild(h2tr);

				let hchours = doc.createElement("th");
				hchours.innerText = "По плану";
				hchours.rowSpan = 2;
				hchours.style.borderRight = "1px solid black";
				hchours.style.borderBottom = "2px solid black";
				hchours.style.fontWeight = "900";
				h2tr.appendChild(hchours);

				let hshours = doc.createElement("th")
				hshours.innerText = "По нагрузке";
				hshours.rowSpan = 2;
				hshours.style.borderRight = "1px solid black";
				hshours.style.borderBottom = "2px solid black";
				hshours.style.fontWeight = "900";
				h2tr.appendChild(hshours);

				let hsubhours = doc.createElement("th");
				hsubhours.innerText = "Снятие";
				hsubhours.colSpan = 2;
				hsubhours.style.borderRight = "2px solid black";
				hsubhours.style.borderBottom = "1px solid black";
				hsubhours.style.fontWeight = "900";
				h2tr.appendChild(hsubhours);

				let h3tr = doc.createElement("tr");
				tab.appendChild(h3tr);

				for (let s = 1; s <= 2; s++)
				{
					let hsemhours = doc.createElement("th");
					hsemhours.innerText = "Семестр " + s;
					hsemhours.colSpan = 6;
					hsemhours.style.borderRight = "2px solid black";
					hsemhours.style.borderBottom = "1px solid black";
					hsemhours.style.fontWeight = "900";
					h2tr.appendChild(hsemhours);

					let hsubhours = doc.createElement("th");
					hsubhours.innerText = "Семестр " + s;
					hsubhours.style.borderRight = s == 2 ? "2px solid black" : "1px solid black";
					hsubhours.style.borderBottom = "2px solid black";
					hsubhours.style.fontWeight = "900";
					h3tr.appendChild(hsubhours);
				}

				for (let s = 1; s <= 2; s++)
				{
					let hhours = doc.createElement("th");
					hhours.innerText = "Всего";
					hhours.style.borderRight = "1px solid black";
					hhours.style.borderBottom = "2px solid black";
					hhours.style.fontWeight = "900";
					h3tr.appendChild(hhours);

					let hwhours = doc.createElement("th");
					hwhours.innerText = "В неделю";
					hwhours.style.borderRight = "1px solid black";
					hwhours.style.borderBottom = "2px solid black";
					hwhours.style.fontWeight = "900";
					h3tr.appendChild(hwhours);

					let hlphours = doc.createElement("th");
					hlphours.innerText = "На ЛПЗ";
					hlphours.style.borderRight = "1px solid black";
					hlphours.style.borderBottom = "2px solid black";
					hlphours.style.fontWeight = "900";
					h3tr.appendChild(hlphours);

					let hcdhours = doc.createElement("th");
					hcdhours.innerText = "На КП";
					hcdhours.style.borderRight = "1px solid black";
					hcdhours.style.borderBottom = "2px solid black";
					hcdhours.style.fontWeight = "900";
					h3tr.appendChild(hcdhours);

					let hcdphours = doc.createElement("th");
					hcdphours.innerText = "На ЗКП";
					hcdphours.style.borderRight = "1px solid black";
					hcdphours.style.borderBottom = "2px solid black";
					hcdphours.style.fontWeight = "900";
					h3tr.appendChild(hcdphours);

					let hehours = doc.createElement("th");
					hehours.innerText = "На Э";
					hehours.style.borderRight = hehours.style.borderBottom = "2px solid black";
					hehours.style.fontWeight = "900";
					h3tr.appendChild(hehours);
				}

				let lnametd = null;
				let rows = StatisticLecturers.elements.load.tBodies[0].rows;

				for (let row of rows)
				{
					let subjtr = doc.createElement("tr");
					tab.appendChild(subjtr);

					let offset = row.classList.contains("subject-name-row") ? 0 : -1;

					for (let cell of row.cells)
					{
						let td = doc.createElement("td");
						td.innerText = cell.innerText;
						td.rowSpan = cell.rowSpan;
						td.style.borderRight = "1px solid black";
						td.style.borderBottom = row == rows[rows.length - 1] ? "2px solid black" : "1px solid black";
						subjtr.appendChild(td);

						if (cell == row.cells[0] && row.classList.contains("subject-name-row"))
						{
							lnametd = td;
							td.style.borderLeft = td.style.borderRight = "2px solid black";
						}

						if (cell == row.cells[1 + offset] || cell == row.cells[5 + offset] || cell == row.cells[11 + offset] || cell == row.cells[17 + offset])
							td.style.borderRight = "2px solid black";

						td.style.textAlign = cell == row.cells[1 + offset] || td == lnametd ? "left" : "right";
					}
				}

				if (lnametd) lnametd.style.borderBottom = "2px solid black";
			}
		});

		let subjects = dialog.AddOption("Нагрузка преподавателя", function(event) { body.Subjects = false; body.src = "about:blank"; });
		dialog.AddOption("Вычитка предметов", function(event) { body.Subjects = true; body.src = "about:blank"; });
		
		dialog.Center();

		subjects.click();
	}

	static Initialize()
	{
		for (let [id, sort] of Object.entries(StatisticLecturers.sortbtns))
		{
			let filter = StatisticLecturers.filters[id];

			StatisticLecturers.columns[parseInt(id)] = {sort: sort, filter: filter};

			sort.addEventListener("click", function(event) {
				sort.Asc = !sort.Asc;
				sort.innerText = sort.Asc ? "▼" : "▲";

				Shared.RemoveFromArray(StatisticLecturers.sort, id);
				StatisticLecturers.sort.unshift(id);

				StatisticLecturers.UpdateSubjects();
			});

			filter?.addEventListener("input", function(event) {
				StatisticLecturers.UpdateSubjects();
			});
		}

		StatisticLecturers.lecturer = Shared.ForeignKeyElement("Lecturers", "ID");
		StatisticLecturers.lecturer.SetRequired(true);
		StatisticLecturers.lecturer.id = "lecturer";
		StatisticLecturers.elements.lecturer.appendChild(StatisticLecturers.lecturer);

		StatisticLecturers.elements.date.addEventListener("change", function(event) {
			let date = StatisticLecturers.validateDate();
			if (date) Shared.SetCookie("statisticlecturers-date", date);
		});

		StatisticLecturers.elements.update.addEventListener("click", function(event) {
			StatisticLecturers.SetupInfo();
			
			if (StatisticLecturers.lecturer.reportValidity() && StatisticLecturers.validateDate())
				StatisticLecturers.OpenInfo();
		});

		StatisticLecturers.elements.export.addEventListener("click", function(event) {
			if (!StatisticLecturers.lecturer.reportValidity()) return;

			StatisticLecturers.ExportStatistic();
		});

		StatisticLecturers.elements.collapse.addEventListener("click", function(event) {
			let collapse = !StatisticLecturers.elements.load.classList.contains("lecturer-load-collapsed");
			StatisticLecturers.elements.collapse.innerText = collapse ? "▲" : "▼";
			
			if (collapse) StatisticLecturers.elements.load.classList.add("lecturer-load-collapsed");
			else StatisticLecturers.elements.load.classList.remove("lecturer-load-collapsed");
		});
		StatisticLecturers.elements.collapse.click();

		if (Shared.GetCookie("statisticlecturers-date"))
			StatisticLecturers.elements.date.value = Shared.GetCookie("statisticlecturers-date");
	}
}
StatisticLecturers.Initialize();