class YearGraphs
{
	static elements = Shared.MapElements({
		open: "#open-btn",
		export: "#export-btn",
		update: "#update-btn",
		clear: "#clear-btn",
		validate: "#validate-btn",
		save: "#save-btn",
		delete: "#delete-btn",
		discrepancy: "#discrepancy-table",
		discrepancyhide: "#discrepancy-table-hide",
		graph: "#graph-container",
		year: "#editor-info-year",
	});

	static graph = new YearActivities();
	static saved = true;
	static lastmsg = null;

	static SetupGraph(year)
	{
		YearGraphs.graph.SetYear(year);
		YearGraphs.graph.SetupTable();
	}

	static OpenGraph(year)
	{
		YearGraphs.lastmsg?.remove();
		let info = Shared.DisplayMessage("Загрузка графика образовательного процесса...", "warning");

		Shared.RequestGET("yeargraph", {
			year: year,
			actdate: Header.GetActualDate(),
			build: 0,
		}, function (data) {
			YearGraphs.LoadGraph(data);
			YearGraphs.saved = true;

			YearGraphs.lastmsg?.remove();
			YearGraphs.lastmsg = Shared.DisplayMessage(`Успешно загружен график на ${data.year} год`, "success");
		}, function (status, code, message) {
			YearGraphs.lastmsg?.remove();
			YearGraphs.lastmsg = Shared.DisplayMessage(`Не удалось загрузить график на ${year} год (${message})`, "error");
		}, function (status, message) {
			YearGraphs.lastmsg?.remove();
			YearGraphs.lastmsg = Shared.DisplayMessage(`Не удалось загрузить график на ${year} год (Ошибка HTTP ${status}: ${message})`, "error");
		}, function (status, message) {
			info.remove();
		});
	}

	static validateYear()
	{
		if (!YearGraphs.elements.year.reportValidity())
			return false;

		let year = parseInt(YearGraphs.elements.year.value);
		if (isNaN(year))
		{
			Shared.DisplayMessage("Неверный формат года", "error");
			return false;
		}
		
		return year;
	}

	static LoadGraph(data)
	{
		YearGraphs.elements.year.value = data.year;

		YearGraphs.graph.ClearRows();
		YearGraphs.SetupGraph(data.year);

		let rowdata = [], custom = [];

		for (let group of data.groups)
		{
			let grp = document.createElement("span");
			grp.GroupID = group.group.id;
			grp.innerText = group.group.fullname;
			
			let course = document.createElement("span");
			course.className = "graph-group-course-" + (group.course ?? 0);
			course.innerText = group.course ?? 0;

			custom.push([grp, course]);
			rowdata.push(group.activities);
		}

		YearGraphs.graph.LoadData(rowdata, custom);
	}

	static ValidateGraph(year)
	{
		if (YearGraphs.graph.year != year)
		{
			Shared.DisplayMessage("График не соответствует году, введенному в поле", "error");
			return false;
		}

		if (YearGraphs.graph.rows.length == 0)
		{
			Shared.DisplayMessage("График пустой", "error");
			return false;
		}

		for (let row of YearGraphs.graph.rows)
		{
			if (row.Activities.length == 0)
			{
				Shared.DisplayMessage(`Группа ${row.Custom[0].innerText} не имеет ни одной деятельности`, "error");
				return false;
			} 

			let nextweek = YearGraphs.graph.startweek;
			
			for (let [aid, act] of row.Activities.entries())
			{
				if (act.Week - nextweek < -0.001)
				{
					Shared.DisplayMessage(`Деятельность #${aid + 1} (${act.ActivityName}) группы ${row.Custom[0].innerText} имеет некорректное начало`, "error");
					return false;
				}

				if (act == row.Activities[row.Activities.length - 1])
				{
					if (YearGraphs.graph.endweek - (act.Week + act.Length) < -0.001)
					{
						Shared.DisplayMessage(`Последняя деятельность группы ${row.Custom[0].innerText} имеет некорректное окончание`, "error");
						return false;
					}		
				}
				else
					nextweek = act.Week + act.Length;
			}
		}

		return true;
	}

	static GetGraphData(year)
	{
		let data = {year: year, groups: []};

		for (let [rid, row] of YearGraphs.graph.GetData().entries())
			data.groups.push({
				group: {id: YearGraphs.graph.rows[rid].Custom[0].GroupID},
				activities: row,
			});

		return data;
	}

	static LoadDiscrepancy(data)
	{
		YearGraphs.elements.discrepancy.hidden = data.length == 0;
		YearGraphs.elements.discrepancy.tBodies[0].innerHTML = "";

		for (let [id, discrepancy] of data.entries())
		{
			let row = document.createElement("tr");
			YearGraphs.elements.discrepancy.tBodies[0].appendChild(row);

			let num = document.createElement("td");
			num.innerText = id + 1;
			row.appendChild(num);

			let group = document.createElement("td");
			group.innerText = discrepancy.group.name;
			row.appendChild(group);

			let act = document.createElement("td");
			act.innerText = discrepancy.activity.name;
			row.appendChild(act);

			let lenreq = document.createElement("td");
			lenreq.innerText = `${discrepancy.required} дней`;
			row.appendChild(lenreq);

			let lencur = document.createElement("td");
			lencur.innerText = `${discrepancy.current} дней`;
			row.appendChild(lencur);

			let lendiff = document.createElement("td");
			lendiff.innerText = `${Math.abs(discrepancy.current - discrepancy.required)} дней`;
			row.appendChild(lendiff);
		}
	}

	static ExportGraph(graph)
	{
		let dialog = Shared.CreateDialog("Предпросмотр и экспорт графика образовательного процесса");

		let body = document.createElement("iframe");
		body.className = "modaldialog-export-frame";
		dialog.Body.appendChild(body);

		body.addEventListener("load", function(event) {
			let doc = body.contentDocument;
			doc.body.innerText = "Обработка конечного графика на " + graph.year + " год...";

			Shared.RequestPOST("export", graph, {
				type: "yeargraph",
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
					Shared.ExportTableToExcel(tab, "График образовательного процесса", `График образовательного процесса на ${graph.year} год`);
				});

				flex.appendChild(exportbtn);
				flex.appendChild(tab);

				let wrows = [];

				for (let i = 0; i < data.wdays; i++)
				{
					let wrow = doc.createElement("tr");
					tab.appendChild(wrow);
					wrows.push(wrow);
				}

				let mrow = doc.createElement("tr");
				tab.appendChild(mrow);

				let wnrow = doc.createElement("tr");
				tab.appendChild(wnrow);

				let frow = wrows[0] ?? mrow;

				let ghead = doc.createElement("th");
				ghead.innerText = "Группа";
				ghead.rowSpan = wrows.length + 2;
				ghead.style.borderTop = ghead.style.borderLeft = ghead.style.borderBottom = "2px solid black";
				ghead.style.borderRight = "1px solid black";
				ghead.style.fontWeight = "900";
				frow.appendChild(ghead);

				let chead = doc.createElement("th");
				chead.innerText = "Курс";
				chead.rowSpan = wrows.length + 2;
				chead.style.borderTop = chead.style.borderBottom = chead.style.borderRight = "2px solid black";
				chead.style.fontWeight = "900";
				frow.appendChild(chead);

				let grows = [];

				for (let [id, group] of data.groups.entries())
				{
					let grow = doc.createElement("tr");
					tab.appendChild(grow);
					grows.push(grow);

					let gname = doc.createElement("td");
					gname.innerText = group.group;
					gname.style.borderLeft = "2px solid black";
					gname.style.borderRight = "1px solid black";
					gname.style.borderBottom = id == data.groups.length - 1 ? "2px solid black" : "1px solid black";
					grow.appendChild(gname);

					let gcourse = doc.createElement("td");
					gcourse.innerText = group.course;
					gcourse.style.borderRight = "2px solid black";
					gcourse.style.borderBottom = id == data.groups.length - 1 ? "2px solid black" : "1px solid black";
					grow.appendChild(gcourse);
				}

				let lmonth = null;
				let months = ["", "Январь", "Февраль", "Март", "Апрель", "Май", "Июнь", "Июль", "Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь"];

				for (let [id, week] of data.weeks.entries())
				{
					for (let [wid, wday] of week.days.entries())
					{
						let wdtd = doc.createElement("td");
						wdtd.innerText = wday;
						if (wid == 0) wdtd.style.borderTop = "2px solid black";
						wdtd.style.borderBottom = "1px solid black";
						wdtd.style.borderRight = id == data.weeks.length - 1 ? "2px solid black" : "1px solid black";
						wrows[wid].appendChild(wdtd);
					}

					let monthn = months[week.month] ?? "";
					if (lmonth && lmonth.innerText == monthn)
						lmonth.colSpan++;
					else
					{
						lmonth = doc.createElement("td");
						lmonth.innerText = monthn;
						lmonth.style.borderBottom = lmonth.style.borderRight = "1px solid black";
						mrow.appendChild(lmonth);
					}

					let wnum = doc.createElement("td");
					wnum.innerText = id + 1;
					wnum.style.borderRight = id == data.weeks.length - 1 ? "2px solid black" : "1px solid black";
					wnum.style.borderBottom = "2px solid black";
					wnrow.appendChild(wnum);

					for (let [gid, group] of data.groups.entries())
					{
						let gact = doc.createElement("td");
						gact.innerText = week.groups[gid];
						gact.style.borderRight = id == data.weeks.length - 1 ? "2px solid black" : "1px solid black";
						gact.style.borderBottom = gid == data.groups.length - 1 ? "2px solid black" : "1px solid black";
						grows[gid].appendChild(gact);
					}
				}

				if (lmonth)
					lmonth.style.borderRight = "2px solid black";

				let spacetd = doc.createElement("td");
				spacetd.style.width = "20px";
				spacetd.rowSpan = wrows.length + 2 + grows.length;
				frow.appendChild(spacetd);

				let getRow = function(id)
				{
					while (!tab.childNodes[id])
					{
						let row = doc.createElement("tr");
						tab.appendChild(row);

						let space = doc.createElement("td");
						space.colSpan = spacetd.cellIndex + 1;
						row.appendChild(space);
					}

					return tab.childNodes[id];
				}

				let dhead = doc.createElement("th");
				dhead.innerText = "Условные обозначения деятельностей";
				dhead.colSpan = 2;
				dhead.style.fontWeight = "900";
				dhead.style.border = "2px solid black";
				getRow(0).appendChild(dhead);

				let dname = doc.createElement("th");
				dname.innerText = "Наименование";
				dname.style.fontWeight = "900";
				dname.style.borderLeft = dname.style.borderRight = dname.style.borderBottom = "2px solid black";
				getRow(1).appendChild(dname);

				let ddesign = doc.createElement("th");
				ddesign.innerText = "Обозначение";
				ddesign.style.fontWeight = "900";
				ddesign.style.borderRight = ddesign.style.borderBottom = "2px solid black";
				getRow(1).appendChild(ddesign);

				for (let [did, design] of data.design.entries())
				{
					let name = doc.createElement("td");
					name.innerText = design[0];
					name.style.textAlign = "left";
					name.style.borderLeft = "2px solid black";
					name.style.borderRight = "1px solid black";
					name.style.borderBottom = did == data.design.length - 1 ? "2px solid black" : "1px solid black";
					getRow(2 + did).appendChild(name);

					let des = doc.createElement("td");
					des.innerText = design[1];
					des.style.textAlign = "right";
					des.style.borderRight = "2px solid black";
					des.style.borderLeft = "1px solid black";;
					des.style.borderBottom = did == data.design.length - 1 ? "2px solid black" : "1px solid black";
					getRow(2 + did).appendChild(des);
				}
			}, function (status, code, message) {
				doc.body.innerText = `Не удалось обработать конечный график (${message})`;
			}, function (status, message) {
				doc.body.innerText = `Не удалось обработать конечный график (Ошибка HTTP ${status}: ${message})`;
			});
		});
		body.src = "about:blank";
	
		dialog.Center();
	}

	static Initialize()
	{
		YearGraphs.graph.SetParent(YearGraphs.elements.graph);
		YearGraphs.graph.AddCustomColumn("Группа");
		YearGraphs.graph.AddCustomColumn("Курс");

		YearGraphs.graph.table.addEventListener("change", function(event) {
			YearGraphs.saved = YearGraphs.graph.rows.length <= 0;
		});

		YearGraphs.elements.open.addEventListener("click", function(event) {
			if (!YearGraphs.saved && !Shared.UnsavedConfirm()) return;

			Shared.QuerySelectList("Выберите график образовательного процесса", "yeargraph", {actdate: Header.GetActualDate()}, function(value, item) {
				item.innerText = `График на ${value} год`;
			}, function(value, item) {
				YearGraphs.OpenGraph(value);
			});
		});

		YearGraphs.elements.export.addEventListener("click", function(event) {
			let year = YearGraphs.validateYear();
			if (!year) return;

			YearGraphs.ExportGraph(YearGraphs.GetGraphData(year));
		});

		YearGraphs.elements.update?.addEventListener("click", function(event) {
			if (!YearGraphs.saved && !Shared.UnsavedConfirm()) return;

			let year = YearGraphs.validateYear();
			if (!year) return;

			if (!confirm("Составить новый график по данным из учебных планов групп?")) return;

			YearGraphs.lastmsg?.remove();
			let info = Shared.DisplayMessage("Получение групп на выбранный год...", "warning");

			Shared.RequestGET("yeargraph", {
				year: year,
				actdate: Header.GetActualDate(),
				build: 1,
			}, function (data) {
				YearGraphs.LoadGraph(data);
				YearGraphs.saved = true;

				YearGraphs.lastmsg?.remove();
				YearGraphs.lastmsg = Shared.DisplayMessage(`Успешно загружены группы на ${data.year} год`, "success");
			}, function (status, code, message) {
				YearGraphs.lastmsg?.remove();
				YearGraphs.lastmsg = Shared.DisplayMessage(`Не удалось загрузить группы на ${year} год (${message})`, "error");
			}, function (status, message) {
				YearGraphs.lastmsg?.remove();
				YearGraphs.lastmsg = Shared.DisplayMessage(`Не удалось загрузить группы на ${year} год (Ошибка HTTP ${status}: ${message})`, "error");
			}, function (status, message) {
				info.remove();
			});
		});

		YearGraphs.elements.clear?.addEventListener("click", function(event) {
			if (!confirm("Очистить все деятельности у групп в таблице?")) return;

			for (let row of YearGraphs.graph.rows)
			{
				row.Clear();
				row.CreateActivity();
				row.Update();
			}
		});

		YearGraphs.elements.validate?.addEventListener("click", function(event) {
			let year = YearGraphs.validateYear();
			if (!year || !YearGraphs.ValidateGraph(year)) return;

			YearGraphs.lastmsg?.remove();
			let info = Shared.DisplayMessage("Идёт проверка графика...", "warning");

			Shared.RequestPOST("yeargraph", YearGraphs.GetGraphData(year), {
				type: "check",
				actdate: Header.GetActualDate(),
			}, function(data) {
				YearGraphs.lastmsg?.remove();

				if (data.length > 0)
					YearGraphs.lastmsg = Shared.DisplayMessage("График не прошел проверку, найдены различия с учебным планом", "error");
				else
					YearGraphs.lastmsg = Shared.DisplayMessage("График успешно прошел проверку", "success");

				YearGraphs.LoadDiscrepancy(data);
			}, function (status, code, message) {
				YearGraphs.lastmsg?.remove();
				YearGraphs.lastmsg = Shared.DisplayMessage(`Не удалось проверить график (${message})`, "error");
			}, function (status, message) {
				YearGraphs.lastmsg?.remove();
				YearGraphs.lastmsg = Shared.DisplayMessage(`Не удалось проверить график (Ошибка HTTP ${status}: ${message})`, "error");
			}, function (status, message) {
				info.remove();
			});
		});

		YearGraphs.elements.discrepancyhide.addEventListener("click", function(event) {
			YearGraphs.elements.discrepancy.hidden = true;
		});

		YearGraphs.elements.save?.addEventListener("click", function(event) {
			let year = YearGraphs.validateYear();
			if (!year || !YearGraphs.ValidateGraph(year)) return;

			if (!confirm(`Сохранить график на ${year} год?`)) return;

			YearGraphs.lastmsg?.remove();
			let info = Shared.DisplayMessage("Идёт сохранение графика...", "warning");

			Shared.RequestPOST("yeargraph", YearGraphs.GetGraphData(year), {
				type: "save",
				actdate: Header.GetActualDate(),
			}, function(data) {
				YearGraphs.lastmsg?.remove();

				if (data.length > 0)
					YearGraphs.lastmsg = Shared.DisplayMessage("График не прошел проверку, найдены различия с учебным планом", "error");
				else
				{
					YearGraphs.lastmsg = Shared.DisplayMessage("График успешно сохранен", "success");
					YearGraphs.saved = true;
				}

				YearGraphs.LoadDiscrepancy(data);
			}, function (status, code, message) {
				YearGraphs.lastmsg?.remove();
				YearGraphs.lastmsg = Shared.DisplayMessage(`Не удалось сохранить график (${message})`, "error");
			}, function (status, message) {
				YearGraphs.lastmsg?.remove();
				YearGraphs.lastmsg = Shared.DisplayMessage(`Не удалось сохранить график (Ошибка HTTP ${status}: ${message})`, "error");
			}, function (status, message) {
				info.remove();
			});
		});

		YearGraphs.elements.delete?.addEventListener("click", function(event) {
			let year = YearGraphs.validateYear();
			if (!year || !confirm(`Удалить график на ${year} год?`)) return;
		
			YearGraphs.lastmsg?.remove();
			let info = Shared.DisplayMessage("Идёт удаление графика...", "warning");

			Shared.RequestPOST("yeargraph", {}, {
				type: "delete",
				actdate: Header.GetActualDate(),
				year: year,
			}, function(data) {
				YearGraphs.lastmsg?.remove();
				YearGraphs.lastmsg = Shared.DisplayMessage("График успешно удален", "success");
			}, function (status, code, message) {
				YearGraphs.lastmsg?.remove();
				YearGraphs.lastmsg = Shared.DisplayMessage(`Не удалось удалить график (${message})`, "error");
			}, function (status, message) {
				YearGraphs.lastmsg?.remove();
				YearGraphs.lastmsg = Shared.DisplayMessage(`Не удалось удалить график (Ошибка HTTP ${status}: ${message})`, "error");
			}, function (status, message) {
				info.remove();
			});
		});

		YearGraphs.elements.year.addEventListener("change", function(event) {
			let year = YearGraphs.validateYear();
			if (year)
			{
				YearGraphs.SetupGraph(year);
				Shared.SetCookie("editor-yeargraph-year", year);
			}
		});

		onbeforeunload = function() {
			if (!YearGraphs.saved)
				return "График не сохранен";
		}

		if (Shared.GetCookie("editor-yeargraph-year"))
			YearGraphs.elements.year.value = Shared.GetCookie("editor-yeargraph-year");

		let year = YearGraphs.validateYear();
		if (year) YearGraphs.OpenGraph(year);

		if (YearGraphs.elements.save)
			window.addEventListener("keydown", function(event) {
				if (!event.ctrlKey || event.code != "KeyS") return;

				event.preventDefault();

				YearGraphs.elements.save.click();
			});
	}
}
YearGraphs.Initialize();