class Shared
{
	static MapElements(selectors)
	{
		let elements = {};

		for (let [k,v] of Object.entries(selectors))
			elements[k] = document.querySelector(v);

		return elements;
	}

	static MapVarElements(selector, datakey)
	{
		let elements = {};

		for (let el of document.querySelectorAll(selector))
			if (el.dataset[datakey]) elements[el.dataset[datakey]] = el;

		return elements;
	}

	static ParseJSON(json)
	{
		let data = null;

		try { data = JSON.parse(json); }
		catch (error) {}

		return data
	}

	static RequestGET(file, params, success, failed, failed2, completed)
	{
		return $.ajax("requests/" + file + ".php", {
			method: "GET",
			data: params,
			dataType: "json",
			success: function(data, status, obj)
			{ if (success) success(data) },
			error: function(obj, status, error)
			{
				let data = Shared.ParseJSON(obj.responseText);
				if (data && failed) failed(obj.status, data.code, data.message);
				else if (!data && failed2) failed2(obj.status, obj.statusText);
			},
			complete: function(obj, status)
			{ if (completed) completed(obj.status, obj.statusText);	},
		})
	}

	static RequestPOST(file, data, params, success, failed, failed2, completed)
	{
		let dt = {};

		for (let [k, v] of Object.entries(params))
			dt[k] = v;

		dt.post = JSON.stringify(data)

		return $.ajax("requests/" + file + ".php", {
			method: "POST",
			data: dt,
			dataType: "json",
			success: function(data, status, obj)
			{ if (success) success(data) },
			error: function(obj, status, error)
			{
				let data = Shared.ParseJSON(obj.responseText);
				if (data && failed) failed(obj.status, data.code, data.message);
				else if (!data && failed2) failed2(obj.status, obj.statusText);
			},
			complete: function(obj, status)
			{ if (completed) completed(obj.status, obj.statusText);	},
		})
	}

	static DisplayMessage(text, type)
	{
		let container = document.querySelector("#message-container");
		if (!container)
		{ alert(text); return; }

		let errbox = document.createElement("div");
		errbox.innerText = text;
		errbox.dataset.type = type;
		container.appendChild(errbox);

		let close =  document.createElement("button");
		close.innerText = "✕";
		close.addEventListener("click", function(event) { errbox.remove(); });
		errbox.appendChild(close);

		return errbox;
	}

	static GetItemListCheckedValues(itemlist)
	{
		let values = {}

		for (let el of itemlist.children)
			if (el.tagName == "LABEL")
			{
				let input = el.lastChild;
				if (input && input.tagName == "INPUT" && input.getAttribute("type") == "checkbox" && input.checked)
					values[input.value] = el.innerText;
			}
			else if (el.tagName == "DIV")
				values[el.dataset.value] = el.innerText;
		
		return values;
	}

	static CreateDialog(title)
	{
		let dialog = document.createElement("dialog");
		dialog.className = "modaldialog";
		document.body.appendChild(dialog);

		dialog.Center = function()
		{
			dialog.style.left = `${document.body.offsetWidth / 2 - dialog.offsetWidth / 2}px`;
			dialog.style.top = `${innerHeight / 2 - dialog.offsetHeight / 2}px`;
		}

		let movable = document.createElement("div");
		movable.className = "modaldialog-movable";
		movable.addEventListener("mousedown", function(event) {
			if (event.button != 0) return;
			event.preventDefault();

			let sleft = dialog.offsetLeft;
			let stop = dialog.offsetTop;
			let move, up;
			
			move = function(event2) {
				dialog.style.left = `${sleft + event2.pageX - event.pageX}px`;
				dialog.style.top = `${stop + event2.pageY - event.pageY}px`;
			};
			up = function(event2) {
				if (event2.button != 0) return;
				event2.preventDefault();

				document.body.removeEventListener("mousemove", move);
				document.body.removeEventListener("mouseup", up);
			};

			document.body.addEventListener("mousemove", move);
			document.body.addEventListener("mouseup", up);
		});
		dialog.appendChild(movable);

		let sizable = document.createElement("div");
		sizable.className = "modaldialog-sizable";
		sizable.addEventListener("mousedown", function(event) {
			if (event.button != 0) return;
			event.preventDefault();

			let swidth = dialog.offsetWidth;
			let sheight = dialog.offsetHeight;
			let resize, up;
			
			resize = function(event2) {
				dialog.style.width = `${swidth + event2.pageX - event.pageX}px`;
				dialog.style.height = `${sheight + event2.pageY - event.pageY}px`;
			};
			up = function(event2) {
				if (event2.button != 0) return;
				event2.preventDefault();

				document.body.removeEventListener("mousemove", resize);
				document.body.removeEventListener("mouseup", up);
			};

			document.body.addEventListener("mousemove", resize);
			document.body.addEventListener("mouseup", up);
		});
		dialog.appendChild(sizable);

		let header = document.createElement("div");
		header.innerText = title;
		header.className = "modaldialog-header";
		dialog.appendChild(header);

		let closebtn = document.createElement("button");
		closebtn.innerText = "✕";
		closebtn.className = "modaldialog-close";
		closebtn.addEventListener("click", function(event) { dialog.remove(); });
		header.appendChild(closebtn);

		let main = document.createElement("div");
		main.className = "modaldialog-main";
		dialog.appendChild(main);

		let body = document.createElement("div");
		body.className = "modaldialog-body";
		main.appendChild(body);

		let options = document.createElement("div");
		options.className = "modaldialog-options";
		main.appendChild(options);

		dialog.Header = header;
		dialog.CloseBtn = closebtn;
		dialog.Body = body;

		dialog.SetWidth = function(width)
		{
			dialog.style.width = `${width * top.GetUIScale() + dialog.offsetWidth - Math.min(dialog.Body.offsetWidth, main.offsetWidth)}px`;
		}

		dialog.SetHeight = function(height)
		{
			dialog.style.height = `${height * top.GetUIScale() + dialog.offsetHeight - Math.min(dialog.Body.offsetHeight, main.offsetHeight)}px`;
		}
		
		dialog.AddOption = function(title, onclick)
		{
			let option = document.createElement("input");
			option.type = "button";
			option.value = title;
			option.addEventListener("click", onclick);

			options.appendChild(option);

			return option;
		}

		dialog.showModal();

		return dialog;
	}

	static DateToSql(date, time)
	{
		if (isNaN(date.getDate())) return null;

		let copy = new Date(date);
		copy.setMinutes(copy.getMinutes() - copy.getTimezoneOffset());

		let parts = copy.toISOString().replace("Z", "").split("T");
		return parts[0] + (time ? " " + parts[1] : "");
	}

	static DateFromSql(date)
	{
		return new Date(date);
	}

	static SetCookie(cookie, value)
	{
		if (value != null && value !== "")
			document.cookie = `${cookie}=${value}`
		else
			document.cookie = `${cookie}=; expires=${new Date().toUTCString()}`
	}

	static GetCookie(cookie)
	{
		let cookies = document.cookie.split("; ")
		
		for (let cook of cookies)
		{
			let info = cook.split("=")

			if (info[0] == cookie) return info[1]
		}
	}

	static RemoveFromArray(array, value)
	{
		let index = array.indexOf(value);

		if (index >= 0)
			array.splice(index, 1);

		return index;
	}

	static ForeignKeyElement(collection, column, filter, special)
	{
		let button = document.createElement("button");
		button.className = "foreign-key-select-btn";

		button.SetRequired = function(required)
		{
			button.required = required;

			if (!required || button.Value) button.classList.remove("fkey-invalid");
			else button.classList.add("fkey-invalid");
		}

		button.reportValidity = function()
		{
			if (button.required && !button.Value)
			{
				button.focus({focusVisible: true});

				if (!button.classList.contains("display-validity"))
				{
					button.classList.add("display-validity");

					if (button.valtimer) clearTimeout(button.valtimer);
					button.valtimer = setTimeout(function() {
						button.classList.remove("display-validity");
					}, 5000);
				}

				return false;
			}

			return true;
		}

		button.SetValue = function(data)
		{
			button.Value = data?.dbvalue ?? data?.id ?? null;
			button.Name = data?.foreignname ?? data?.name ?? "-";
			button.FullName = data?.foreignfname ?? data?.fullname ?? "Нет значения";

			button.innerText = button.Name;
			button.title = button.FullName;

			if (!button.required || button.Value) button.classList.remove("fkey-invalid");
			else button.classList.add("fkey-invalid");

			Shared.BubbleEvent(button, "change");
		}

		button.SetValue();

		button.clickevent = function(event) {
			let spec = special;

			if (spec && typeof spec[1] == "function")
				spec = [spec[0], spec[1]()];

			Shared.SelectForeignKey(collection, function(row) {
				let value = row?.Cells[column].Input.GetDataValue();
				let name = row?.Name;
				let fname = row?.FullName;

				if (!Shared.BubbleEvent(button, "fkeyselect", {row: row, value: value, name: name, fullname: fname}))
					return;

				button.SetValue({dbvalue: value, foreignname: name, foreignfname: fname});
			}, filter, spec);
		};

		button.addEventListener("click", button.clickevent);

		return button;
	}

	static SelectForeignKey(collection, onselect, filter, special)
	{
		let dialog = Shared.CreateDialog("Выбор внешнего объекта");

		let placeholder = document.createElement("div");
		placeholder.innerText = "Загрузка страницы выбора внешнего объекта...";
		placeholder.className = "modaldialog-placeholder";
		dialog.Body.appendChild(placeholder);

		let url = "collections.php?fkeyselect=1&collection=" + collection;

		if (filter)
			for (let [column, value] of Object.entries(filter))
				url += "&filter-" + column + "=" + encodeURI(value);

		Shared.SetCookie("collection-fkey-special-type", special ? special[0] : null);
		Shared.SetCookie("collection-fkey-special-info", special ? JSON.stringify(special[1]) : null);

		let frame = document.createElement("iframe");
		frame.src = url;
		frame.hidden = true;
		frame.addEventListener("load", function(event2) {
			frame.hidden = false;
			placeholder.remove();

			frame.contentWindow.OnCollectionRowsUpdated = function(rows)
			{
				for (let row of rows)
					if (!row.NameInput.Foreign)
					{	
						row.NameInput.Foreign = true;
						row.NameInput.addEventListener("click", function(event3) {
							dialog.remove();

							onselect(row);
						});
					}
			}
		});
		dialog.Body.appendChild(frame);

		dialog.AddOption("Очистить значение", function(event2) {
			dialog.remove();

			onselect(null);
		});

		dialog.Center();
	}

	static QuerySelectList(title, file, params, onadd, onselect)
	{
		let dialog = Shared.CreateDialog(title);

		let placeholder = document.createElement("div");
		placeholder.innerText = "Получение списка значений...";
		placeholder.className = "modaldialog-placeholder";
		dialog.Body.appendChild(placeholder);

		Shared.RequestGET(file, params, function(data) {
			if (data.length == 0)
			{
				placeholder.innerText = "Значений для выбора не найдено";
				return;
			}

			placeholder.remove();

			let container = document.createElement("div");
			container.className = "modaldialog-selectlist-container";
			dialog.Body.appendChild(container);

			for (let item of data)
			{
				let btn = document.createElement("button");
				btn.innerText = item.toString();
				container.appendChild(btn);

				if (onadd) onadd(item, btn);

				btn.addEventListener("click", function(event) {
					dialog.remove();

					if (onselect) onselect(item, btn);
				});
			}

			dialog.SetHeight(container.scrollHeight);
			dialog.Center();
		}, function(status, code, message) {
			placeholder.innerText = `Не удалось получить список значений (${message})`;
		}, function (status, message) {
			placeholder.innerText = `Не удалось получить список значений (Ошибка HTTP ${status}: ${message})`;
		});

		dialog.SetWidth(1000);
		dialog.SetHeight(50);

		dialog.Center();
	}

	static MakeTableAsItemList(table, cells, maincell, additem, onadd, onremove, compact)
	{
		table.Items = [];

		let addrow = function()
		{
			let row = document.createElement("tr");
			table.tBodies[0].appendChild(row);

			for (let i = 0; i < cells; i++)
			{
				let cell = document.createElement("td");
				row.appendChild(cell);
			}

			return row;
		}

		let create = document.createElement("button");
		create.className = "table-item-list-create";

		if (compact)
		{
			create.innerText = "+";
			table.hidden = true;
			table.after(create);
		}
		else
		{
			create.innerText = "Добавить";
			create.Row = addrow();
			create.Row.cells[maincell].appendChild(create);
		}

		table.AddItem = function(item)
		{
			let row = addrow();
			let cell = row.cells[maincell];

			let grid = document.createElement("div");
			grid.className = "table-item-list-grid";
			table.Items.push(grid);
			grid.Item = item;
			grid.Row = row;
			cell.appendChild(grid);

			let remove = document.createElement("button");
			remove.className = "table-item-list-remove";
			remove.innerText = "✕";

			grid.appendChild(remove);
			grid.RemoveBtn = remove;

			grid.appendChild(item);

			grid.Remove = function()
			{
				row.remove();
				Shared.RemoveFromArray(table.Items, grid);

				if (compact && table.Items.length == 0)
					table.hidden = true;

				if (onremove) onremove(row, grid, item);

				Shared.BubbleEvent(table, "change", {item: grid, removed: true});
			}

			grid.Move = function(down)
			{
				let index = table.Items.indexOf(grid);
				if (index == -1 || (down ? index == table.Items.length - 1 : index == 0)) return;

				let index2 = down ? index + 1 : index - 1;
				let swap = table.Items[index2];

				table.Items[index] = swap;
				table.Items[index2] = grid;

				if (down) swap.Row.after(row);
				else row.after(swap.Row);

				if (grid.OnMove) grid.OnMove(swap);

				Shared.BubbleEvent(table, "change", {item: grid, removed: false});
				Shared.BubbleEvent(table, "change", {item: swap, removed: false});
			}
			
			remove.addEventListener("click", function() {
				grid.Remove();
			});

			if (!compact)
				table.tBodies[0].appendChild(create.Row);
			else
				table.hidden = false;

			if (onadd) onadd(row, grid, item);

			Shared.BubbleEvent(table, "change", {item: grid, removed: false});

			return grid;
		}

		table.ClearItems = function()
		{
			for (let item of table.Items)
				item.Row.remove();

			table.Items.splice(0);

			if (compact) table.hidden = true;
			if (onremove) onremove();

			Shared.BubbleEvent(table, "change", {removed: true});
		}

		create.addEventListener("click", function(event) {
			let item = additem(table.AddItem);
			if (item) table.AddItem(item);
		});
	}

	static ContextMenu(parent, items, onopen)
	{
		let menu = document.createElement("ul");
		menu.className = "context-menu";
		menu.style.position = "absolute";
		menu.hidden = true;
		menu.Items = [];
		document.body.appendChild(menu);

		for (let item of items)
		{
			let it = document.createElement("li");
			menu.appendChild(it);

			let elem = document.createElement("button");
			elem.innerText = item.text;
			it.appendChild(elem);

			if (item.type == "button" && item.onclick)
				elem.addEventListener("click", function(event) {
					menu.hidden = true;

					if (item.onclick) item.onclick(event);
				});
			else if (item.type == "checkbox")
			{
				elem.classList.add("context-menu-item-checkbox");
				elem.SetChecked = function(checked)
				{
					elem.Checked = checked;

					if (checked) elem.classList.add("context-menu-item-checked");
					else elem.classList.remove("context-menu-item-checked");
				}

				elem.SetChecked(item.default ?? false);

				elem.addEventListener("click", function(event) {
					elem.SetChecked(!elem.Checked);

					if (item.onchange) item.onchange(elem.Checked);
				});
			}

			menu.Items.push(elem);
		}

		parent.addEventListener("contextmenu", function(event) {
			if (event.target != parent) return;

			event.preventDefault();

			menu.hidden = false;

			if (onopen) onopen(event);

			let left = event.pageX;
			if (left + menu.offsetWidth > innerWidth) left -= menu.offsetWidth;

			let top = event.pageY;
			if (top + menu.offsetHeight > innerHeight) top -= menu.offsetHeight;

			menu.style.left = `${left}px`;
			menu.style.top = `${top}px`;
		});

		document.documentElement.addEventListener("mousedown", function(event) {
			if (!menu.hidden && !menu.contains(event.target)) menu.hidden = true;
		});

		document.documentElement.addEventListener("keydown", function(event) {
			if (!menu.hidden && event.key == "Escape") menu.hidden = true;
		});

		return menu;
	}

	static Remap(value, omin, omax, nmin, nmax)
	{
		return nmin + (value - omin) / (omax - omin) * (nmax - nmin);
	}

	static BubbleEvent(element, event, params)
	{
		return element.dispatchEvent(new CustomEvent(event, {bubbles: true, cancelable: true, detail: params ?? {}}));
	}

	static UnsavedConfirm()
	{
		return confirm("Есть несохраненные изменения. Продолжить?");
	}

	static ExportTableToExcel(table, title, fname)
	{
		$(table).table2excel({
			name: title,
			filename: `${fname}.xls`,
			preserveColors: true,
		});
	}
}

if (top.UpdateScaleUI)
	top.UpdateScaleUI(window);