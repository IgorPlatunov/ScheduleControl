class Collections
{
	static elements = Shared.MapElements({
		name: "#collection-name",
		collection: "#collection-table",
		head: "#collection-table > thead",
		table: "#collection-table > tbody",

		update: "#collection-actions-update",
		save: "#collection-actions-save",
		addrow: "#collection-actions-addrow",
		view: "#collection-actions-view",
		pkeys: "#collection-actions-pkeys",
		width: "#collection-actions-width",

		limit: "#collection-options-limit",
		page: "#collection-options-page",
		ignorefilter: "#collection-options-ignorespecfilter",

		objheader: "#collection-table-object",
		sortobj: "#collection-table-sort-object",
		search: "#collection-table-search",

		selectall: "#collection-table-actions-select",
		deleteall: "#collection-table-actions-delete",
		hideall: "#collection-table-actions-hide",
		copyall: "#collection-table-actions-copy",
	});

	static lastmsg = null;
	static ColumnsInfo = null;
	static Rows = [];
	static ColumnHeaders = Shared.MapVarElements("#collection-table-columns > th", "column");
	static ColumnSorts = Shared.MapVarElements(".collection-table-sort-btn", "column");
	static ColumnFilters = Shared.MapVarElements(".collection-table-filter", "column");
	static SortPriority = [];

	static write = false;
	static config = false;

	static UpdateRows()
	{
		Collections.ClearRows();
		Collections.OnRowsUpdated();

		let info = Shared.DisplayMessage("Обновление данных коллекции регистров...", "warning");
		Collections.lastmsg?.remove();

		let page = parseInt(Collections.elements.page.value);
		if (isNaN(page)) page = 1;

		Shared.RequestGET("registrycollectiondata", {
			name: Collections.elements.name.dataset.name,
			actdate: top.GetActualDate(),
			specialtype: Collections.elements.collection.dataset.fkey && !Collections.elements.ignorefilter.checked && Shared.GetCookie("collection-fkey-special-type") || undefined,
			specialinfo: Collections.elements.collection.dataset.fkey && !Collections.elements.ignorefilter.checked && Shared.GetCookie("collection-fkey-special-info") || undefined,
			limit: Collections.elements.limit.value,
			offset: (page - 1) * Collections.elements.limit.value,
		}, function (data) {
			Collections.ClearRows();

			Collections.ColumnsInfo = data.columns;

			for (let rowdata of data.rows)
			{
				let row = Collections.AddRow(rowdata);
				Collections.elements.table.appendChild(row);
				Collections.Rows.push(row);
			}

			Collections.OnRowsUpdated();

			Collections.lastmsg?.remove();
			Collections.lastmsg = Shared.DisplayMessage(`Получено строк: ${data.rows.length}`, "success");
		}, function (status, code, message) {
			Collections.lastmsg?.remove();
			Collections.lastmsg = Shared.DisplayMessage(`Не удалось обновить данные (${message})`, "error");
		}, function (status, message) {
			Collections.lastmsg?.remove();
			Collections.lastmsg = Shared.DisplayMessage(`Не удалось обновить данные (Ошибка HTTP ${status}: ${message})`, "error");
		}, function (status, message) {
			info.remove();
		});
	}

	static ClearRows()
	{
		for (let row of Collections.Rows)
			row.remove();

		Collections.Rows.splice(0);
	}

	static AddRow(rowdata, newrow)
	{
		let row = document.createElement("tr");
		row.className = "collection-table-row";
		row.Added = newrow;
		row.Cells = {};
		if (newrow) row.classList.add("row-added");

		row.Select = function(select) {
			row.Selected = select;
			row.SelectBox.checked = select;
			
			if (select) row.classList.add("row-selected");
			else row.classList.remove("row-selected");

			if (!select && row.Deleted)
				row.DeleteBtn.click();
		}

		if (Collections.write && !Collections.config)
			row.Delete = function(delet) {
				if (newrow) return;

				row.Deleted = delet;

				if (delet) row.DeleteBtn.classList.add("row-for-delete");
				else row.DeleteBtn.classList.remove("row-for-delete");

				if (delet && !row.Selected)
					row.SelectBox.click();
			}

		row.Remove = function() {
			row.remove();

			Shared.RemoveFromArray(Collections.Rows, row);
		}

		let actions = document.createElement("td");
		row.appendChild(actions);

		let actbody = document.createElement("div");
		actbody.className = "collection-table-row-actions";
		actions.appendChild(actbody);

		let select = document.createElement("input");
		select.className = "iconbtn collection-table-row-select";
		select.type = "checkbox";
		select.title = "Выбрать строку";
		select.addEventListener("click", function(event) {
			row.Select(select.checked);

			Collections.elements.selectall.Update();
		});
		actbody.appendChild(select);

		row.SelectBox = select;
		if (newrow) row.Select(true);

		if (Collections.write && !Collections.config)
		{
			let delet = document.createElement("button");
			delet.className = "iconbtn collection-table-row-delete";
			delet.title = "Пометить на удаление";
			delet.addEventListener("click", function(event) {
				row.Delete(!row.Deleted);

				Collections.elements.deleteall.Update();
			})
			actbody.appendChild(delet);

			row.DeleteBtn = delet;
			if (newrow) delet.disabled = true;
		}
		
		let hide = document.createElement("button");
		hide.className = "iconbtn collection-table-row-hide";
		hide.title = "Скрыть строку";
		hide.addEventListener("click", function(event) {
			if (!confirm("Удалить строку из таблицы? Это действие не удаляет строку из базы данных.")) return;

			row.Remove();
			Collections.OnRowsUpdated();
		})
		actbody.appendChild(hide);

		if (Collections.write && !Collections.config)
		{
			let copy = document.createElement("button");
			copy.className = "iconbtn collection-table-row-copy";
			copy.title = "Скопировать строку";
			copy.addEventListener("click", function(event) {
				Collections.CopyRow(row);
				Collections.OnRowsUpdated();
			});
			actbody.appendChild(copy);
		}

		let name = document.createElement("td");
		name.scope = "row";
		row.appendChild(name);

		let nameinput = document.createElement("button");
		nameinput.innerHTML = newrow ? "Новый объект" : rowdata.name;
		nameinput.title = newrow ? "Объект добавлен, но ещё не сохранён" : rowdata.fullname;
		nameinput.className = "collection-table-row-object-btn";
		nameinput.addEventListener("click", function(event) { select.click(); });
		name.appendChild(nameinput);

		row.NameInput = nameinput;
		row.Name = rowdata?.name;
		row.FullName = rowdata?.fullname;

		if (newrow) nameinput.disabled = true;

		for (let coldata of Collections.ColumnsInfo)
		{
			let cell = Collections.AddCell(rowdata?.cells[coldata.name], coldata, newrow);
			row.appendChild(cell);
			row.Cells[coldata.name] = cell;

			cell.addEventListener("change", function(event) {
				if (!row.Selected) select.click();
			});
		}

		return row;
	}

	static AddCell(celldata, coldata, newrow)
	{
		let cell = document.createElement("td");
		cell.OriginalValue = celldata?.dbvalue;

		if (coldata.foreign)
		{
			let input = Shared.ForeignKeyElement(coldata.foreign.table, coldata.foreign.column);
			input.SetRequired(true);

			if (celldata && (!newrow || !coldata.locked)) input.SetValue(celldata);
			
			cell.appendChild(input);
			cell.Input = input;
		}
		else
		{
			let input = document.createElement("input");
			input.type = coldata.inputtype;
			input.required = input.type != "text" && input.type != "checkbox";

			for (let [attr, value] of Object.entries(coldata.inputlimits))
				input.setAttribute(attr, value);

			if (celldata && (!newrow || !coldata.locked))
				if (coldata.inputtype == "checkbox")
					input.checked = celldata.dbvalue == 1;
				else
					input.value = celldata.dbvalue;

			cell.appendChild(input);
			cell.Input = input;

			cell.Input.addEventListener("input", function() { cell.dispatchEvent(new CustomEvent("change")); })
		}

		cell.Input.GetStringValue = function()
		{
			if (coldata.foreign)
				return cell.Input.Name;
			else if (coldata.inputtype == "checkbox")
				return cell.Input.checked ? "1" : "0";
			else if (coldata.inputtype == "datetime-local")
			{
				let date = new Date(cell.Input.value);
				return date.toLocaleDateString() + " " + date.toLocaleTimeString();
			}
			else if (coldata.inputtype == "date")
			{
				let date = new Date(cell.Input.value);
				return date.toLocaleDateString();
			}

			return cell.Input.value.toString();
		}

		cell.Input.GetDataValue = function()
		{
			if (coldata.foreign)
				return cell.Input.Value;
			else if (coldata.inputtype == "checkbox")
				return cell.Input.checked ? 1 : 0;
			else if (coldata.inputtype == "datetime-local")
			{
				let date = new Date(cell.Input.value);
				return Shared.DateToSql(date, true);
			}
			else if (coldata.inputtype == "date")
			{
				let date = new Date(cell.Input.value);
				return Shared.DateToSql(date);
			}

			return cell.Input.value;
		}
		
		cell.Validate = function()
		{
			if (coldata.foreign) return cell.Input.Value != null;
			else return cell.Input.reportValidity();
		}

		if (!coldata.data && !newrow || coldata.locked) cell.Input.disabled = true;

		return cell;
	}

	static OnRowsUpdated()
	{
		Collections.elements.selectall.Update();

		if (Collections.write && !Collections.config)
			Collections.elements.deleteall.Update();

		Collections.UpdateView();
		Collections.UpdateFilter();
		Collections.UpdateSort();

		if (typeof OnCollectionRowsUpdated != "undefined")
			OnCollectionRowsUpdated(Collections.Rows);
	}

	static UpdateView()
	{
		let view = Collections.elements.view.dataset.view;

		for (let header of Object.values(Collections.ColumnHeaders))
			for (let row of Collections.elements.collection.rows)
				row.cells[header.cellIndex].hidden = view=="list" || header.dataset.pkey && (!Collections.write || !Collections.elements.pkeys.Show);

		for (let row of Collections.elements.collection.rows)
			row.cells[Collections.elements.objheader.cellIndex].hidden = view=="table";
	}

	static UpdateFilter()
	{
		let search = Collections.elements.search.value.toLowerCase();
		let filters = {};

		for (let [column, input] of Object.entries(Collections.ColumnFilters))
			filters[column] = input.value.toLowerCase();

		for (let row of Collections.Rows)
		{
			if (row.Added) continue;

			let hide = false;

			if (search != "")
			{
				hide = true;

				if (row.Name.indexOf(search) >= 0 || row.FullName.indexOf(search) >= 0)
					hide = false;
				else
					for (let cell of Object.values(row.Cells))
						if (cell.Input.GetStringValue().toLowerCase().indexOf(search) >= 0)
							{ hide = false; break; }
			}

			if (!hide)
				for (let [column, cell] of Object.entries(row.Cells))
					if (filters[column] != "" && cell.Input.GetStringValue().toString().toLowerCase().indexOf(filters[column]) < 0)
					{ hide = true; break; }

			row.hidden = hide;
		}
	}

	static UpdateSort()
	{
		Collections.Rows.sort(function(a, b) {
			if (a.Added && b.Added) return 0;
			if (a.Added || b.Added) return a.Added ? -1 : 1;

			for (let column of Collections.SortPriority)
			{
				let vala = column == "" ? a.Name : a.Cells[column].Input.GetStringValue();
				let valb = column == "" ? b.Name : b.Cells[column].Input.GetStringValue();

				if (vala != valb)
				{
					let sort = column == "" ? Collections.elements.sortobj : Collections.ColumnSorts[column];

					return (vala > valb ? 1 : -1)*(sort.SortAsc ? 1 : -1);
				}
			}

			return 0;
		});

		for (let row of Collections.Rows)
			Collections.elements.table.appendChild(row);
	}

	static GetSelectedRows()
	{
		let rows = [];

		for (let row of Collections.Rows)
			if (row.Selected) rows.push(row);

		return rows;
	}

	static CopyRow(row, dontpush)
	{
		let data = {name: null, cells: {}};
			
		for (let [name, cell] of Object.entries(row.Cells))
			data.cells[name] = {dbvalue: cell.Input.GetDataValue(), foreignname: cell.Input.Name ?? null};

		let nrow = Collections.AddRow(data, true);
		Collections.elements.table.appendChild(nrow);

		if (!dontpush) Collections.Rows.unshift(nrow);

		return nrow;
	}

	static Initialize()
	{
		Collections.write = Collections.elements.save != null;
		Collections.config = Collections.write && Collections.elements.addrow == null;

		Collections.elements.update.addEventListener("click", function(event) {
			if (Collections.write && Collections.GetSelectedRows().length > 0 && !Shared.UnsavedConfirm()) return;

			Collections.UpdateRows();
		});
		
		Collections.elements.selectall.Update = function() {
			let selected = 0;
			let selectable = 0;
		
			for (let row of Collections.Rows)
			{
				if (!row.hidden) selectable++;
				if (row.Selected && !row.hidden) selected++;
			}
		
			Collections.elements.selectall.checked = selected > 0 && selected >= selectable;
			Collections.elements.selectall.indeterminate = selected > 0 && selected < selectable;
		}
		
		Collections.elements.selectall.addEventListener("click", function(event) {
			let active = Collections.elements.selectall.checked;
		
			for (let row of Collections.Rows)
				if (!row.hidden) row.Select(active);
		
			Collections.elements.selectall.Update();
		});
		
		if (Collections.write)
		{
			if (!Collections.config)
			{
				Collections.elements.deleteall.Update = function() {
					let deleted = 0;
					let deletable = 0;
				
					for (let row of Collections.Rows)
					{
						if (!row.Added && !row.hidden) deletable++;
						if (row.Deleted && !row.hidden) deleted++;
					}
				
					let deleteall = Collections.elements.deleteall;
					deleteall.Deleted = deleted > 0 && deleted >= deletable;
				
					deleteall.classList.remove("row-for-delete");
					deleteall.classList.remove("row-for-delete-part");
				
					if (deleteall.Deleted) deleteall.classList.add("row-for-delete");
					else if (deleted > 0) deleteall.classList.add("row-for-delete-part");
				}
			
				Collections.elements.deleteall.addEventListener("click", function(event) {
					let deleted = !Collections.elements.deleteall.Deleted;
				
					for (let row of Collections.Rows)
						if (!row.hidden) row.Delete(deleted);
				
					Collections.elements.deleteall.Update();
				});

				Collections.elements.copyall.addEventListener("click", function(event) {
					let rows = Collections.GetSelectedRows();
					if (rows.length == 0) { alert("Не выбраны строки для копирования"); return; }
				
					for (let id in rows)
					{
						let nrow = Collections.CopyRow(rows[id], true);
						Collections.Rows.splice(id, 0, nrow);

						rows[id].Select(false);
					}
				
					Collections.OnRowsUpdated();
				});
			}

			Collections.elements.pkeys.addEventListener("click", function(event) {
				let pkeys = Collections.elements.pkeys;
				pkeys.Show = !pkeys.Show;
	
				Shared.SetCookie("collection-pkeys", pkeys.Show ? 1 : 0);
			
				if (pkeys.Show) pkeys.classList.add("pkey-show");
				else pkeys.classList.remove("pkey-show");
			
				Collections.UpdateView();
			});

			if (!Collections.config)
				Collections.elements.addrow.addEventListener("click", function(event) {
					let row = Collections.AddRow(null, true);
					Collections.elements.table.appendChild(row);
					Collections.Rows.unshift(row);
				
					Collections.OnRowsUpdated();
				});
			
			Collections.elements.save.addEventListener("click", function(event) {
				let rows = Collections.GetSelectedRows();
				if (rows.length == 0) { alert("Не выбраны строки для сохранения"); return; }
				if (!confirm(`Сохранить строки в базу данных? Выделено: ${rows.length}.`)) return;
	
				let invalid = 0;
	
				for (let row of rows)
					for (let cell of Object.values(row.Cells))
						if (!cell.Validate()) invalid++;
	
				if (invalid > 0)
				{
					Collections.lastmsg?.remove();
					Collections.lastmsg = Shared.DisplayMessage(`Найдено ${invalid} несоответствий данных полей формату.`, "error");
	
					return;
				}
	
				let data = [];
			
				for (let row of rows)
				{
					let rowdata = {old: row.Added ? null : {}, new: row.Deleted ? null : {}};
			
					for (let [column, cell] of Object.entries(row.Cells))
					{
						if (!row.Added) rowdata.old[column] = cell.OriginalValue;
						if (!row.Deleted) rowdata.new[column] = cell.Input.GetDataValue();
					}
			
					data.push(rowdata);
				}
			
				let info = Shared.DisplayMessage("Сохранение изменений данных регистров...", "warning");
				Collections.lastmsg?.remove();
			
				Shared.RequestPOST("registrycollectiondata", data, {
					name: Collections.elements.name.dataset.name,
					actdate: top.GetActualDate(),
				}, function (data) {
					Collections.lastmsg?.remove();
					Shared.DisplayMessage(`Успешно сохранено строк: ${rows.length}`, "success");
			
					Collections.UpdateRows();
				}, function (status, code, message) {
					Collections.lastmsg?.remove();
					Collections.lastmsg = Shared.DisplayMessage(`Не удалось сохранить данные (${message})`, "error");
				}, function (status, message) {
					Collections.lastmsg?.remove();
					Collections.lastmsg = Shared.DisplayMessage(`Не удалось сохранить данные (Ошибка HTTP ${status}: ${message})`, "error");
				}, function (status, message) {
					info.remove();
				});
			});

			window.addEventListener("keydown", function(event) {
				if (!event.ctrlKey || event.code != "KeyS") return;

				event.preventDefault();

				Collections.elements.save.click();
			});
		}
		
		Collections.elements.hideall.addEventListener("click", function(event) {
			let rows = Collections.GetSelectedRows();
			if (rows.length == 0) { alert("Не выбрано строк для скрытия"); return; }
		
			if (!confirm("Вы уверены, что хотите скрыть выделенные строки?")) return;
		
			for (let row of rows)
				row.Remove();
		
			Collections.OnRowsUpdated();
		});
		
		Collections.elements.view.addEventListener("click", function(event) {
			Collections.elements.view.dataset.view = Collections.elements.view.dataset.view == "table" ? "list" : "table";
			Shared.SetCookie("collection-view", Collections.elements.view.dataset.view);
		
			Collections.UpdateView();
		});
		
		Collections.elements.search.addEventListener("input", function(event) {
			Collections.UpdateFilter();

			Collections.elements.selectall.Update();

			if (Collections.write)
				Collections.elements.deleteall.Update();
		});
		
		for (let column of Object.values(Collections.ColumnFilters))
			column.addEventListener("input", function(event) {
				Collections.UpdateFilter();

				Collections.elements.selectall.Update();

				if (Collections.write)
					Collections.elements.deleteall.Update();
			});
		
		Collections.elements.sortobj.addEventListener("click", function(event) {
			Collections.elements.sortobj.SortAsc = !Collections.elements.sortobj.SortAsc;
			Collections.elements.sortobj.innerText = Collections.elements.sortobj.SortAsc ? "▼" : "▲";
		
			Shared.RemoveFromArray(Collections.SortPriority, "");
			Collections.SortPriority.unshift("");
		
			Collections.UpdateSort();
		});
		
		for (let [column, sort] of Object.entries(Collections.ColumnSorts))
			sort.addEventListener("click", function(event) {
				sort.SortAsc = !sort.SortAsc;
				sort.innerText = sort.SortAsc ? "▼" : "▲";
		
				Shared.RemoveFromArray(Collections.SortPriority, column);
				Collections.SortPriority.unshift(column);
		
				Collections.UpdateSort();
			});
		
		Collections.elements.width.addEventListener("click", function(event) {
			if (Collections.elements.collection.classList.contains("table-width"))
				Collections.elements.collection.classList.remove("table-width");
			else
				Collections.elements.collection.classList.add("table-width");

			Shared.SetCookie("collection-width", Collections.elements.collection.classList.contains("table-width") ? 0 : 1);
		});

		Collections.elements.limit.addEventListener("change", function(event) {
			Shared.SetCookie("collection-limit", Collections.elements.limit.value);

			if (Collections.elements.page.reportValidity())
				Collections.UpdateRows();
		});

		Collections.elements.page.addEventListener("change", function(event) {
			if (Collections.elements.page.reportValidity())
				Collections.UpdateRows();
		});

		Collections.elements.ignorefilter?.addEventListener("change", function(event) {
			Shared.SetCookie("collection-fkey-ignorefilter", Collections.elements.ignorefilter.checked ? 1 : 0);
		});

		if (!Collections.elements.collection.dataset.fkey && Shared.GetCookie("collection-view"))
		{
			Collections.elements.view.dataset.view = Shared.GetCookie("collection-view");
			Collections.UpdateView();
		}

		if (Collections.write && Shared.GetCookie("collection-pkeys") == 1)
			Collections.elements.pkeys.click();

		if (Shared.GetCookie("collection-width") == 1)
			Collections.elements.width.click();

		if (Shared.GetCookie("collection-limit"))
			Collections.elements.limit.value = Shared.GetCookie("collection-limit");

		if (Collections.elements.ignorefilter && Shared.GetCookie("collection-fkey-ignorefilter") == 1)
			Collections.elements.ignorefilter.checked = true;
		
		Collections.UpdateRows();

		onbeforeunload = function() {
			if (Collections.write)
				for (let row of Collections.Rows)
					if (row.Selected)
						return "Есть несохраненный строки";
		}
	}
}
Collections.Initialize();