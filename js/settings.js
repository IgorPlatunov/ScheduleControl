class Settings
{
	static elements = Shared.MapElements({
		usertheme: "#user-theme-list",
		userlist: "#user-select-list",
		userlogin: "#user-login-input",
		userpass: "#user-password-input",
		userpassnew: "#user-password-change",
		userroles: "#user-roles-list",
		userprivs: "#user-privileges-list",
		verifypass: "#current-password",
		savebtn: "#user-save-btn",
		delbtn: "#user-del-btn",
		newbtn: "#user-new-btn",
		updatedb: "#super-update-db",
		clearold: "#super-clear-old-data",
	});

	static roles = {};
	static privileges = {};
	static users = {};
	static cuser = null;
	static requestactive = false;

	static LoadUser(user)
	{
		Settings.cuser = user;
	
		Settings.elements.userlogin.value = user.login;
		Settings.elements.userpass.value = "";
		Settings.elements.userpassnew.checked = false;
	
		Settings.LoadUserRoles();
		Settings.LoadUserPrivileges();
	
		if (!Settings.requestactive)
			Settings.ChangeRequestState(false);
	}
	
	static LoadUserRoles()
	{
		if (!Settings.cuser) return;
	
		let html = "";
	
		if (Settings.elements.userlist)
			for (let [role, data] of Object.entries(Settings.roles))
				html += `<label>${Settings.roles[role].name}<input type="checkbox" ${Settings.cuser.roles[role] ? "checked" : ""} value="${role}"></label>`;
		else
			for (let role of Object.keys(Settings.cuser.roles))
				if (Settings.roles[role])
					html += `<div data-value="${role}">${Settings.roles[role].name}</div>`;
		
		Settings.elements.userroles.innerHTML = html;
	}
	
	static LoadUserPrivileges()
	{
		if (!Settings.cuser) return;
	
		let croles = Shared.GetItemListCheckedValues(Settings.elements.userroles);
		let privs = {};
	
		for (let [priv, privn] of Object.entries(Settings.privileges))
			for (let [role, data] of Object.entries(Settings.roles))
				if (croles[role] && data.privileges[priv])
					privs[priv] = privn;
	
		let html = "";
	
		for (let [priv, privn] of Object.entries(privs))
			html += `<div data-value="${priv}">${privn}</div>`;
	
		Settings.elements.userprivs.innerHTML = html;
	}
	
	static LoadUsersList(data)
	{
		Settings.users = data;
	
		let html = "";
	
		for (let user of Settings.users)
			html += `<option value="${user.id}">${user.login}</option>`;
	
		Settings.elements.userlist.innerHTML = html;
	
		if (Settings.cuser)
			Settings.elements.userlist.value = Settings.cuser.id;
	}
	
	static ChangeRequestState(active)
	{
		Settings.requestactive = active;
	
		if (active)
		{
			Settings.elements.savebtn.setAttribute("disabled", "");
			Settings.elements.delbtn?.setAttribute("disabled", "");
			Settings.elements.newbtn?.setAttribute("disabled", "");
		}
		else
		{
			Settings.elements.savebtn.removeAttribute("disabled");
			Settings.elements.delbtn?.removeAttribute("disabled");
			Settings.elements.newbtn?.removeAttribute("disabled");
		}
	}
	
	static UpdateUserList()
	{
		if (!Settings.elements.userlist) return
	
		let userswarn = Shared.DisplayMessage("Список пользователей загружается...", "warning");
	
		Shared.RequestGET("users", {type: "all"}, function(data) {
			Settings.LoadUsersList(data);
		}, function(status, code, message) {
			Shared.DisplayMessage(`Не удалось получить данные о пользователях (${message})`, "error");
		}, function(status, error) {
			Shared.DisplayMessage(`Не удалось получить данные о пользователях (Ошибка HTTP ${status}: ${error})`, "error");
		}, function(status, message) {
			userswarn.remove();
		});
	}

	static Initialize()
	{
		Settings.elements.userroles.addEventListener("change", function(event) { Settings.LoadUserPrivileges(); });

		Settings.elements.savebtn.addEventListener("click", function(event) {
			if (!Settings.cuser) return;
			if (!Settings.elements.verifypass.reportValidity()) return;
			
			if (Settings.elements.userlist)
			{ if (!confirm("Сохранить внесенные изменения данных выбранного пользователя?")) return; }
			else
			{ if (!confirm("Сохранить внесенные изменения данных?")) return; }


			let data = {};
			
			if (Settings.elements.userlogin.value != Settings.cuser.login) data.login = Settings.elements.userlogin.value;
			if (Settings.elements.userpassnew.checked) data.password = Settings.elements.userpass.value;

			let sendroles = false;
			let nroles = Shared.GetItemListCheckedValues(Settings.elements.userroles);
			for (let role of Object.keys(nroles))
				if (!Settings.cuser.roles[role]) { sendroles = true; break; }

			if (!sendroles)
				for (let role of Object.keys(Settings.cuser.roles))
					if (!nroles[role]) { sendroles = true; break; }

			if (sendroles) data.roles = Object.keys(nroles);

			Settings.ChangeRequestState(true);

			Shared.RequestPOST("users", data, {type: "update", userid: Settings.cuser.id, password: Settings.elements.verifypass.value}, function(data) {
				Shared.DisplayMessage("Изменения успешно сохранены", "success");
				Settings.UpdateUserList();
			}, function(status, code, message) {
				Shared.DisplayMessage(`Не удалось сохранить изменения (${message})`, "error");
			}, function(status, error) {
				Shared.DisplayMessage(`Не удалось сохранить изменения (Ошибка HTTP ${status}: ${error})`, "error");
			}, function(status, message) {
				Settings.ChangeRequestState(false);
			});
		});

		Settings.elements.delbtn?.addEventListener("click", function(event) {
			if (!Settings.cuser) return;
			if (!Settings.elements.verifypass.reportValidity()) return;
			if (!confirm(`Вы уверены, что хотите удалить пользователя ${Settings.cuser.login}?`)) return;
		
			Settings.ChangeRequestState(true);
		
			Shared.RequestPOST("users", {}, {type: "delete", userid: Settings.cuser.id, password: Settings.elements.verifypass.value}, function(data) {
				Shared.DisplayMessage("Пользователь успешно удален", "success");
				Settings.UpdateUserList();
			}, function(status, code, message) {
				Shared.DisplayMessage(`Не удалось удалить пользователя (${message})`, "error");
			}, function(status, error) {
				Shared.DisplayMessage(`Не удалось удалить пользователя (Ошибка HTTP ${status}: ${error})`, "error");
			}, function(status, message) {
				Settings.ChangeRequestState(false);
			});
		});

		Settings.elements.newbtn?.addEventListener("click", function(event) {
			if (!Settings.cuser) return;
			if (!Settings.elements.verifypass.reportValidity()) return;
			if (!confirm("Это действие создаст нового пользователя по данным из полей. Продолжить?")) return;
		
			let data = {
				login: Settings.elements.userlogin.value,
				password: Settings.elements.userpass.value,
				roles: Object.keys(Shared.GetItemListCheckedValues(Settings.elements.userroles)),
			};
		
			Settings.ChangeRequestState(true);
		
			Shared.RequestPOST("users", data, {type: "create", password: Settings.elements.verifypass.value}, function(data) {
				Settings.cuser.id = data.id;

				Shared.DisplayMessage("Пользователь успешно создан", "success");
				Settings.UpdateUserList();
			}, function(status, code, message) {
				Shared.DisplayMessage(`Не удалось создать пользователя (${message})`, "error");
			}, function(status, error) {
				Shared.DisplayMessage(`Не удалось создать пользователя (Ошибка HTTP ${status}: ${error})`, "error");
			}, function(status, message) {
				Settings.ChangeRequestState(false);
			});
		});

		Settings.elements.userlist?.addEventListener("change", function(event) {
			for (let user of Settings.users)
				if (user.id == Settings.elements.userlist.value)
				{ Settings.LoadUser(user); break; }
		});

		let roleswarn = Shared.DisplayMessage("Данные о ролях и правах загружаются...", "warning");
		Shared.RequestGET("roles", {type: "all"}, function(data) {
			Settings.roles = data;

			for (let [role, info] of Object.entries(data))
				for (let [priv, privn] of Object.entries(info.privileges))
					Settings.privileges[priv] = privn;

			Settings.LoadUserRoles();
			Settings.LoadUserPrivileges();
		}, function(status, code, message) {
			Shared.DisplayMessage(`Не удалось получить данные о ролях (${message})`, "error");
		}, function(status, error) {
			Shared.DisplayMessage(`Не удалось получить данные о ролях (Ошибка HTTP ${status}: ${error})`, "error");
		}, function(status, message) {
			roleswarn.remove();
		});

		Settings.UpdateUserList();

		let userwarn = Shared.DisplayMessage("Данные о текущем пользователе загружаются...", "warning");
		Shared.RequestGET("users", {type: "self"}, function(data) {
			Settings.LoadUser(data);

			if (Settings.elements.userlist)
				Settings.elements.userlist.value = data.id;
		}, function(status, code, message) {
			Shared.DisplayMessage(`Не удалось получить данные о текущем пользователе (${message})`, "error");
		}, function(status, error) {
			Shared.DisplayMessage(`Не удалось получить данные о текущем пользователе (Ошибка HTTP ${status}: ${error})`, "error");
		}, function(status, message) {
			userwarn.remove();
		});

		Settings.elements.updatedb?.addEventListener("click", function(event) {
			if (!confirm("Обновить структуру базы данных на сервере? Структура будет адаптирована под конфигурацию таблиц и коллекций регистров, определенную в модулях сервера")) return;

			let warn = Shared.DisplayMessage("Идёт обновление структуры базы данных...", "warning");
			Shared.RequestPOST("updatedb", {}, {}, function(data) {
				Shared.DisplayMessage("Обновление структуры базы данных выполнено успешно", "success");
			}, function(status, code, message) {
				Shared.DisplayMessage(`Не удалось обновить структуру базы данных (${message})`, "error");
			}, function(status, error) {
				Shared.DisplayMessage(`Не удалось обновить структуру базы данных (Ошибка HTTP ${status}: ${error})`, "error");
			}, function(status, message) {
				warn.remove();
			});
		});

		Settings.elements.clearold?.addEventListener("click", function(event) {
			if (!confirm("Вы уверены, что хотите удалить старые версии объектов регистров? Это очистит базу данных от версий, созданных более года назад")) return;

			let warn = Shared.DisplayMessage("Выполняется удаление старых версий объектов регистров...", "warning");
			Shared.RequestPOST("clearoldchanges", {}, {actdate: Header.GetActualDate()}, function(data) {
				Shared.DisplayMessage("Старые версии объектов регистров успешно удалены", "success");
			}, function(status, code, message) {
				Shared.DisplayMessage(`Не удалось удалить старые версии объектов регистров (${message})`, "error");
			}, function(status, error) {
				Shared.DisplayMessage(`Не удалось удалить старые версии объектов регистров (Ошибка HTTP ${status}: ${error})`, "error");
			}, function(status, message) {
				warn.remove();
			});
		});
	}
}
Settings.Initialize();