class SubjectRoomCategory
{
	static elements = Shared.MapElements({
		clear: "#clear-btn",
		open: "#open-btn",
		save: "#save-btn",
		delete: "#delete-btn",
		name: "#editor-info-name",
		abbr: "#editor-info-abbr",
		subjects: "#subjects-table",
		rooms: "#rooms-table",
		editorinfo: "#editor-info",
		objectsinfo: "#objects-info",
	});

	static CategoryID = null;
	static saved = true;
	static lastmsg = null;

	static OpenCategory(id)
	{
		SubjectRoomCategory.lastmsg?.remove();
		let info = Shared.DisplayMessage(`Загружается категория предметов и кабинетов...`, "warning");

		Shared.RequestGET("subjectroomcategory", {
			id: id,
			actdate: Header.GetActualDate(),
		}, function(data) {
			SubjectRoomCategory.LoadCategory(data);

			SubjectRoomCategory.lastmsg?.remove();
			SubjectRoomCategory.lastmsg = Shared.DisplayMessage(`Загружена категория ${data.name}`, "success");
		}, function (status, code, message) {
			SubjectRoomCategory.lastmsg?.remove();
			SubjectRoomCategory.lastmsg = Shared.DisplayMessage(`Не удалось загрузить категорию (${message})`, "error");
		}, function (status, message) {
			SubjectRoomCategory.lastmsg?.remove();
			SubjectRoomCategory.lastmsg = Shared.DisplayMessage(`Не удалось загрузить категорию (Ошибка HTTP ${status}: ${message})`, "error");
		}, function (status, message) {
			info.remove();
		});
	}

	static LoadCategory(data)
	{
		SubjectRoomCategory.CategoryID = data?.id?.id;
		
		if (SubjectRoomCategory.CategoryID)
			Shared.SetCookie("editor-subjectroomcategory-id", SubjectRoomCategory.CategoryID);

		if (SubjectRoomCategory.elements.delete)
			SubjectRoomCategory.elements.delete.disabled = !SubjectRoomCategory.CategoryID;

		SubjectRoomCategory.elements.name.value = data?.name ?? "";
		SubjectRoomCategory.elements.abbr.value = data?.abbr ?? "";

		SubjectRoomCategory.elements.subjects.ClearItems();
		SubjectRoomCategory.elements.rooms.ClearItems();

		if (data)
		{
			for (let subject of data.subjects)
			{
				let item = SubjectRoomCategory.elements.subjects.NewItem();
				item.SetValue(subject);

				SubjectRoomCategory.elements.subjects.AddItem(item);
			}

			for (let room of data.rooms)
			{
				let item = SubjectRoomCategory.elements.rooms.NewItem();
				item.SetValue(room);

				SubjectRoomCategory.elements.rooms.AddItem(item);
			}
		}

		SubjectRoomCategory.saved = true;
	}

	static GetCategory()
	{
		let data = {
			id: SubjectRoomCategory.CategoryID ? {id: SubjectRoomCategory.CategoryID} : null,
			name: SubjectRoomCategory.elements.name.value,
			abbr: SubjectRoomCategory.elements.abbr.value,
			subjects: [],
			rooms: [],
		};

		for (let item of SubjectRoomCategory.elements.subjects.Items)
			if (item.Item.Value)
				data.subjects.push({id: item.Item.Value});

		for (let item of SubjectRoomCategory.elements.rooms.Items)
			if (item.Item.Value)
				data.rooms.push({id: item.Item.Value});

		return data;
	}

	static Initialize()
	{
		let subjects = SubjectRoomCategory.elements.subjects;
		let rooms = SubjectRoomCategory.elements.rooms;

		subjects.NewItem = function()
		{
			let item = Shared.ForeignKeyElement("Subjects", "ID", null, [0, function() {
				let subjs = [];

				for (let item of subjects.Items)
					if (item.Item.Value)
						subjs.push(item.Item.Value);

				return subjs;
			}]);
			item.SetRequired(true);

			return item;
		}

		rooms.NewItem = function()
		{
			let item = Shared.ForeignKeyElement("Rooms", "ID", null, [0, function() {
				let roms = [];

				for (let item of rooms.Items)
					if (item.Item.Value)
						roms.push(item.Item.Value);

				return roms;
			}]);
			item.SetRequired(true);

			return item;
		}

		Shared.MakeTableAsItemList(subjects, 1, 0, function() {
			let item = subjects.NewItem();
			item.click();

			return item;
		});

		Shared.MakeTableAsItemList(rooms, 1, 0, function() {
			let item = rooms.NewItem();
			item.click();

			return item;
		});

		SubjectRoomCategory.elements.editorinfo.addEventListener("change", function(event) {
			SubjectRoomCategory.saved = false;
		});

		SubjectRoomCategory.elements.objectsinfo.addEventListener("change", function(event) {
			SubjectRoomCategory.saved = false;
		});

		SubjectRoomCategory.elements.open.addEventListener("click", function(event) {
			if (!SubjectRoomCategory.saved && !Shared.UnsavedConfirm()) return;

			Shared.SelectForeignKey("SubjectRoomCategories", function(row) {
				if (!row) return;

				let id = row.Cells.ID.Input.GetDataValue();
				SubjectRoomCategory.OpenCategory(id);
			});
		});

		SubjectRoomCategory.elements.clear?.addEventListener("click", function(event) {
			if (!SubjectRoomCategory.saved && !Shared.UnsavedConfirm()) return;

			SubjectRoomCategory.LoadCategory();
		});

		SubjectRoomCategory.elements.save?.addEventListener("click", function(event) {
			if (!SubjectRoomCategory.elements.name.reportValidity()) return;
			if (!SubjectRoomCategory.elements.abbr.reportValidity()) return;

			if (!confirm("Сохранить текущую категорию предметов и кабинетов?")) return;

			let data = SubjectRoomCategory.GetCategory();

			SubjectRoomCategory.lastmsg?.remove();
			let info = Shared.DisplayMessage(`Категория ${data.name} сохраняется...`, "warning");

			SubjectRoomCategory.elements.save.disabled = true;

			Shared.RequestPOST("subjectroomcategory", data, {
				type: "save",
				actdate: Header.GetActualDate(),
			}, function(data) {
				SubjectRoomCategory.CategoryID = data.id;
				SubjectRoomCategory.saved = true;
				SubjectRoomCategory.elements.delete.disabled = false;

				Shared.SetCookie("editor-subjectroomcategory-id", data.id);

				SubjectRoomCategory.lastmsg?.remove();
				Shared.DisplayMessage(`Категория ${data.name} успешно сохранена`, "success");
			}, function (status, code, message) {
				SubjectRoomCategory.lastmsg?.remove();
				SubjectRoomCategory.lastmsg = Shared.DisplayMessage(`Не удалось сохранить категорию (${message})`, "error");
			}, function (status, message) {
				SubjectRoomCategory.lastmsg?.remove();
				SubjectRoomCategory.lastmsg = Shared.DisplayMessage(`Не удалось сохранить категорию (Ошибка HTTP ${status}: ${message})`, "error");
			}, function (status, message) {
				info.remove();
				SubjectRoomCategory.elements.save.disabled = false;
			});
		});

		SubjectRoomCategory.elements.delete?.addEventListener("click", function(event) {
			if (!SubjectRoomCategory.CategoryID || !confirm("Вы уверены, что хотите удалить данную категорию?")) return;

			SubjectRoomCategory.lastmsg?.remove();
			let info = Shared.DisplayMessage(`Категория удаляется...`, "warning");

			Shared.RequestPOST("subjectroomcategory", {}, {
				id: SubjectRoomCategory.CategoryID,
				type: "delete",
				actdate: Header.GetActualDate(),
			}, function(data) {
				SubjectRoomCategory.LoadCategory();

				SubjectRoomCategory.lastmsg?.remove();
				Shared.DisplayMessage(`Категория успешно удалена`, "success");
			}, function (status, code, message) {
				SubjectRoomCategory.lastmsg?.remove();
				SubjectRoomCategory.lastmsg = Shared.DisplayMessage(`Не удалось удалить категорию (${message})`, "error");
			}, function (status, message) {
				SubjectRoomCategory.lastmsg?.remove();
				SubjectRoomCategory.lastmsg = Shared.DisplayMessage(`Не удалось удалить категорию (Ошибка HTTP ${status}: ${message})`, "error");
			}, function (status, message) {
				info.remove();
			});
		});

		SubjectRoomCategory.LoadCategory();

		if (Shared.GetCookie("editor-subjectroomcategory-id"))
			SubjectRoomCategory.OpenCategory(Shared.GetCookie("editor-subjectroomcategory-id"));

		if (SubjectRoomCategory.elements.save)
			window.addEventListener("keydown", function(event) {
				if (!event.ctrlKey || event.code != "KeyS") return;

				event.preventDefault();

				if (!SubjectRoomCategory.elements.save.disabled)
					SubjectRoomCategory.elements.save.click();
			});
	}
}
SubjectRoomCategory.Initialize();