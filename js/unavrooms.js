class UnAvRooms
{
	static elements = Shared.MapElements({
		add: "#add-btn",
		update: "#update-btn",
		room: "#room-container",
		addsection: "#new-unav",
		from: "#new-unav-from",
		to: "#new-unav-to",
		period: "#new-unav-period",
		comment: "#new-unav-comment",
		length: "#new-unav-period-length",
		customlab: `label[for="new-unav-period-custom"]`,
		custom: "#new-unav-period-custom",
		first: "#new-unav-period-first",
		firstend: "#new-unav-period-first-end",
		periodcont: "#new-unav-period-container",
		unavs: "#unavs-table",
		unavsbody: "#unavs-table-body",
	});

	static room = Shared.ForeignKeyElement("Rooms", "ID");
	static lastmsg = null;
	static saved = true;

	static LoadUnAvs(data, room)
	{
		UnAvRooms.elements.unavsbody.innerHTML = "";

		if (data)
			for (let [id, unav] of data.entries())
			{
				let row = document.createElement("tr");
				UnAvRooms.elements.unavsbody.appendChild(row);

				let idtd = document.createElement("td");
				idtd.innerText = id + 1;
				row.appendChild(idtd);

				let count = document.createElement("td");
				count.innerText = unav.count;
				row.appendChild(count);

				let start = document.createElement("td");
				start.innerText = UnAvRooms.DateString(unav.start);
				row.appendChild(start);

				let end = document.createElement("td");
				end.innerText = UnAvRooms.DateString(unav.end);
				row.appendChild(end);

				let comment = document.createElement("td");
				comment.innerText = unav.comment;
				row.appendChild(comment);

				row.addEventListener("click", function(event) {
					Shared.SelectForeignKey("NotAvailableRooms", function() {}, {Room: room, NotAvalID: unav.id});
				});
			}
	}

	static OpenUnAvs(room, name)
	{
		UnAvRooms.elements.unavsbody.innerHTML = "";

		UnAvRooms.lastmsg?.remove();
		let info = Shared.DisplayMessage(`Загружаются недоступности кабинета ${name}...`, "warning");

		Shared.RequestGET("unavrooms", {
			actdate: Header.GetActualDate(),
			room: room,
		}, function(data) {
			UnAvRooms.LoadUnAvs(data, name);

			UnAvRooms.lastmsg?.remove();
			UnAvRooms.lastmsg = Shared.DisplayMessage(`Загружены недоступности кабинета ${name}`, "success");
		}, function (status, code, message) {
			UnAvRooms.lastmsg?.remove();
			UnAvRooms.lastmsg = Shared.DisplayMessage(`Не удалось загрузить недоступности кабинета ${name} (${message})`, "error");
		}, function (status, message) {
			UnAvRooms.lastmsg?.remove();
			UnAvRooms.lastmsg = Shared.DisplayMessage(`Не удалось загрузить недоступности кабинета ${name} (Ошибка HTTP ${status}: ${message})`, "error");
		}, function (status, message) {
			info.remove();
		});
	}

	static DateString(date)
	{
		return new Date(date).toLocaleTimeString("ru-RU", {weekday: "short", year: "numeric", month: "long", day: "numeric"});
	}

	static Initialize()
	{
		UnAvRooms.room.SetRequired(true);
		UnAvRooms.room.id = "lecturer";
		UnAvRooms.elements.room.appendChild(UnAvRooms.room);

		UnAvRooms.room.addEventListener("change", function(event) {
			if (UnAvRooms.room.reportValidity())
				UnAvRooms.OpenUnAvs(UnAvRooms.room.Value, UnAvRooms.room.Name);
		});

		UnAvRooms.elements.addsection?.addEventListener("change", function(event) {
			UnAvRooms.saved = false;
		});

		UnAvRooms.elements.period?.addEventListener("change", function(event) {
			UnAvRooms.elements.periodcont.hidden = !UnAvRooms.elements.period.checked;
		});

		UnAvRooms.elements.length?.addEventListener("change", function(event) {
			UnAvRooms.elements.customlab.hidden =  UnAvRooms.elements.custom.hidden = UnAvRooms.elements.length.value != "2";
		});

		UnAvRooms.elements.update.addEventListener("click", function(event) {
			if (UnAvRooms.room.reportValidity())
				UnAvRooms.OpenUnAvs(UnAvRooms.room.Value, UnAvRooms.room.Name);
		});

		UnAvRooms.elements.add?.addEventListener("click", function(event) {
			if (
				!UnAvRooms.room.reportValidity() ||
				!UnAvRooms.elements.from.reportValidity() ||
				!UnAvRooms.elements.to.reportValidity() ||
				UnAvRooms.elements.period.checked && (
					!UnAvRooms.elements.first.reportValidity() ||
					!UnAvRooms.elements.firstend.reportValidity() ||
					UnAvRooms.elements.length.value == "2" && !UnAvRooms.elements.custom.reportValidity()
				)
			) return;

			let data = {
				from: UnAvRooms.elements.from.value,
				to: UnAvRooms.elements.to.value,
				period: UnAvRooms.elements.period.checked ? 1 : 0,
				comment: UnAvRooms.elements.comment.value,
			};

			let text = `Сохранить новую недоступность кабинета?

Кабинет: ${UnAvRooms.room.Name}
Начало действия: ${UnAvRooms.DateString(data.from)}
Окончание действия: ${UnAvRooms.DateString(data.to)}
Комментарий: ${data.comment}`;

			if (data.period == 1)
			{
				data.first = UnAvRooms.elements.first.value;
				data.firstend = UnAvRooms.elements.firstend.value;
				data.length = UnAvRooms.elements.length.value;
				
				if (data.length == 2)
					data.custom = UnAvRooms.elements.custom.value;

text += `

Периодичность: ${data.length == 2 ? `${data.custom} часов` : (UnAvRooms.elements.length.selectedOptions[0]?.innerText ?? "???")}
Начало первой недоступности: ${UnAvRooms.DateString(data.first)}
Окончание первой недоступности: ${UnAvRooms.DateString(data.firstend)}`;
			}

			if (!confirm(text)) return;

			let room = UnAvRooms.room.Value;
			let name = UnAvRooms.room.Name;
			
			UnAvRooms.lastmsg?.remove();
			let info = Shared.DisplayMessage(`Добавляются недоступности кабинета ${name}...`, "warning");

			Shared.RequestPOST("unavrooms", data, {
				actdate: Header.GetActualDate(),
				room: room,
			}, function(data) {
				UnAvRooms.lastmsg?.remove();
				Shared.DisplayMessage(`Успешно добавлено ${data.count} недоступностей кабинета ${name}`, "success");

				UnAvRooms.OpenUnAvs(room, name);
				UnAvRooms.saved = true;
			}, function (status, code, message) {
				UnAvRooms.lastmsg?.remove();
				UnAvRooms.lastmsg = Shared.DisplayMessage(`Не удалось добавить недоступности кабинета ${name} (${message})`, "error");
			}, function (status, message) {
				UnAvRooms.lastmsg?.remove();
				UnAvRooms.lastmsg = Shared.DisplayMessage(`Не удалось добавить недоступности кабинета ${name} (Ошибка HTTP ${status}: ${message})`, "error");
			}, function (status, message) {
				info.remove();
			});
		});

		onbeforeunload = function(event)
		{
			if (!UnAvRooms.saved)
				return "Недоступности не сохранены";
		};
	}
}
UnAvRooms.Initialize();