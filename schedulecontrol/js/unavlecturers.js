class UnAvLecturers
{
	static elements = Shared.MapElements({
		add: "#add-btn",
		update: "#update-btn",
		lecturer: "#lecturer-container",
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

	static lecturer = Shared.ForeignKeyElement("Lecturers", "ID");
	static lastmsg = null;
	static saved = true;

	static LoadUnAvs(data, lect)
	{
		UnAvLecturers.elements.unavsbody.innerHTML = "";

		if (data)
			for (let [id, unav] of data.entries())
			{
				let row = document.createElement("tr");
				UnAvLecturers.elements.unavsbody.appendChild(row);

				let idtd = document.createElement("td");
				idtd.innerText = id + 1;
				row.appendChild(idtd);

				let count = document.createElement("td");
				count.innerText = unav.count;
				row.appendChild(count);

				let start = document.createElement("td");
				start.innerText = UnAvLecturers.DateString(unav.start);
				row.appendChild(start);

				let end = document.createElement("td");
				end.innerText = UnAvLecturers.DateString(unav.end);
				row.appendChild(end);

				let comment = document.createElement("td");
				comment.innerText = unav.comment;
				row.appendChild(comment);

				row.addEventListener("click", function(event) {
					Shared.SelectForeignKey("NotAvailableLecturers", function() {}, {Lecturer: lect, NotAvalID: unav.id});
				});
			}
	}

	static OpenUnAvs(lecturer, name)
	{
		UnAvLecturers.elements.unavsbody.innerHTML = "";

		UnAvLecturers.lastmsg?.remove();
		let info = Shared.DisplayMessage(`Загружаются отсутствия преподавателя ${name}...`, "warning");

		Shared.RequestGET("unavlecturers", {
			actdate: Header.GetActualDate(),
			lecturer: lecturer,
		}, function(data) {
			UnAvLecturers.LoadUnAvs(data, name);

			UnAvLecturers.lastmsg?.remove();
			UnAvLecturers.lastmsg = Shared.DisplayMessage(`Загружены отсутствия преподавателя ${name}`, "success");
		}, function (status, code, message) {
			UnAvLecturers.lastmsg?.remove();
			UnAvLecturers.lastmsg = Shared.DisplayMessage(`Не удалось загрузить отсутствия преподавателя ${name} (${message})`, "error");
		}, function (status, message) {
			UnAvLecturers.lastmsg?.remove();
			UnAvLecturers.lastmsg = Shared.DisplayMessage(`Не удалось загрузить отсутствия преподавателя ${name} (Ошибка HTTP ${status}: ${message})`, "error");
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
		UnAvLecturers.lecturer.SetRequired(true);
		UnAvLecturers.lecturer.id = "lecturer";
		UnAvLecturers.elements.lecturer.appendChild(UnAvLecturers.lecturer);

		UnAvLecturers.lecturer.addEventListener("change", function(event) {
			if (UnAvLecturers.lecturer.reportValidity())
				UnAvLecturers.OpenUnAvs(UnAvLecturers.lecturer.Value, UnAvLecturers.lecturer.Name);
		});

		UnAvLecturers.elements.addsection?.addEventListener("change", function(event) {
			UnAvLecturers.saved = false;
		});

		UnAvLecturers.elements.period?.addEventListener("change", function(event) {
			UnAvLecturers.elements.periodcont.hidden = !UnAvLecturers.elements.period.checked;
		});

		UnAvLecturers.elements.length?.addEventListener("change", function(event) {
			UnAvLecturers.elements.customlab.hidden =  UnAvLecturers.elements.custom.hidden = UnAvLecturers.elements.length.value != "2";
		});

		UnAvLecturers.elements.update.addEventListener("click", function(event) {
			if (UnAvLecturers.lecturer.reportValidity())
				UnAvLecturers.OpenUnAvs(UnAvLecturers.lecturer.Value, UnAvLecturers.lecturer.Name);
		});

		UnAvLecturers.elements.add?.addEventListener("click", function(event) {
			if (
				!UnAvLecturers.lecturer.reportValidity() ||
				!UnAvLecturers.elements.from.reportValidity() ||
				!UnAvLecturers.elements.to.reportValidity() ||
				UnAvLecturers.elements.period.checked && (
					!UnAvLecturers.elements.first.reportValidity() ||
					!UnAvLecturers.elements.firstend.reportValidity() ||
					UnAvLecturers.elements.length.value == "2" && !UnAvLecturers.elements.custom.reportValidity()
				)
			) return;

			let data = {
				from: UnAvLecturers.elements.from.value,
				to: UnAvLecturers.elements.to.value,
				period: UnAvLecturers.elements.period.checked ? 1 : 0,
				comment: UnAvLecturers.elements.comment.value,
			};

			let text = `Сохранить новое отсутствие преподавателя?

Преподаватель: ${UnAvLecturers.lecturer.Name}
Начало действия: ${UnAvLecturers.DateString(data.from)}
Окончание действия: ${UnAvLecturers.DateString(data.to)}
Комментарий: ${data.comment}`;

			if (data.period == 1)
			{
				data.first = UnAvLecturers.elements.first.value;
				data.firstend = UnAvLecturers.elements.firstend.value;
				data.length = UnAvLecturers.elements.length.value;
				
				if (data.length == 2)
					data.custom = UnAvLecturers.elements.custom.value;

text += `

Периодичность: ${data.length == 2 ? `${data.custom} часов` : (UnAvLecturers.elements.length.selectedOptions[0]?.innerText ?? "???")}
Начало первого отсутствия: ${UnAvLecturers.DateString(data.first)}
Окончание первого отсутствия: ${UnAvLecturers.DateString(data.firstend)}`;
			}

			if (!confirm(text)) return;

			let lecturer = UnAvLecturers.lecturer.Value;
			let name = UnAvLecturers.lecturer.Name;
			
			UnAvLecturers.lastmsg?.remove();
			let info = Shared.DisplayMessage(`Добавляются отсутствия преподавателя ${name}...`, "warning");

			Shared.RequestPOST("unavlecturers", data, {
				actdate: Header.GetActualDate(),
				lecturer: lecturer,
			}, function(data) {
				UnAvLecturers.lastmsg?.remove();
				Shared.DisplayMessage(`Успешно добавлено ${data.count} отсутствий преподавателя ${name}`, "success");

				UnAvLecturers.OpenUnAvs(lecturer, name);
				UnAvLecturers.saved = false;
			}, function (status, code, message) {
				UnAvLecturers.lastmsg?.remove();
				UnAvLecturers.lastmsg = Shared.DisplayMessage(`Не удалось добавить отсутствия преподавателя ${name} (${message})`, "error");
			}, function (status, message) {
				UnAvLecturers.lastmsg?.remove();
				UnAvLecturers.lastmsg = Shared.DisplayMessage(`Не удалось добавить отсутствия преподавателя ${name} (Ошибка HTTP ${status}: ${message})`, "error");
			}, function (status, message) {
				info.remove();
			});
		});

		onbeforeunload = function(event)
		{
			if (!UnAvLecturers.saved)
				return "Отсутствия не сохранены";
		};
	}
}
UnAvLecturers.Initialize();