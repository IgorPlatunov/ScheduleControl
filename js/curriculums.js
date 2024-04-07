class Curriculums {
	static elements = Shared.MapElements({
		add: "#add-btn",
		remove: "#remove-btn",
		clear: "#clear-btn",
		copy: "#copy-btn",
		open: "#open-btn",
		save: "#save-btn",
		delete: "#delete-btn",

		editorinfo: "#editor-info",
		course: "#editor-info-course",
		course2: "#editor-info-course2",
		name: "#editor-info-name",
		year: "#editor-info-year",
		qualification: "#editor-info-qualif-container",
		educationlevel: "#editor-info-edlevel-container",

		budgettable: "#budget-table",
		budgetdesign: "#budget-designations",

		graph: "#graph-container",

		subjects: "#subjects-table",
		filter: "#subjects-filter",
	});
	
	static qualification = Shared.ForeignKeyElement("Qualifications", "ID");
	static educationlevel = Shared.ForeignKeyElement("EducationLevels", "ID");
	static graph = new YearActivities();
	static CurriculumID = null;

	static graphs = {};
	static budget = {};
	static subjects = {};
	static course = null;
	static saved = true;
	static lastmsg = null;
	static autosave = null;

	static validateYear()
	{
		if (!Curriculums.elements.year.reportValidity())
			return false;

		return parseInt(Curriculums.elements.year.value);
	}

	static GetCourseYear()
	{
		return Curriculums.validateYear() + parseInt(Curriculums.course) - (Curriculums.elements.course2.checked ? 2 : 1);
	}

	static SwitchCourse(course)
	{
		let saved = Curriculums.saved;

		if (Curriculums.course != null)
			Curriculums.ExportCourse();

		Curriculums.course = course ?? 1;
		Curriculums.ImportCourse();

		Curriculums.saved = saved;
	}

	static ImportCourse()
	{
		let course = Curriculums.course;
		let gdata = Curriculums.graphs[course];

		Curriculums.graph.ClearRows();
		Curriculums.graph.SetYear(Curriculums.GetCourseYear());

		let graphcourse = document.createElement("span");
		graphcourse.innerText = course;

		if (gdata) Curriculums.graph.LoadData([gdata], [[graphcourse]]);
		else
		{
			Curriculums.graph.SetupTable();
			Curriculums.graph.AddRow([graphcourse]);
		}

		Curriculums.elements.budgettable.ClearItems();
		
		if (Curriculums.budget[course])
			for (let activity of Object.values(Curriculums.budget[course]))
			{
				if (!activity.activity) continue;

				let item = Curriculums.elements.budgettable.NewActivityItem();
				item.SetValue(activity.activity);
				item.Weeks = Math.round(activity.length * 10) / 10;

				Curriculums.elements.budgettable.AddItem(item);
			}

		Curriculums.elements.budgetdesign.Update();
		Curriculums.elements.subjects.ClearItems();

		for (let [id, subject] of Object.entries(Curriculums.subjects))
		{
			let item = Curriculums.elements.subjects.NewSubjectItem();
			item.SetValue(subject.subject);
			item.Semesters = subject.courses[course];
			item.SubjectID = id;

			Curriculums.elements.subjects.AddItem(item);
		}

		let nooption = true;
		
		for (let option of Curriculums.elements.course.options)
			if (option.value == course) {
				Curriculums.elements.course.value = course;

				nooption = false;
				break;
			}

		if (nooption)
		{
			let option = document.createElement("option");
			option.value = course;
			option.innerText = course;

			let prevoption = null;
			for (let opt of Curriculums.elements.course.options)
				if (opt.value < course && (!prevoption || prevoption.value < opt.value))
					prevoption = opt;

			if (prevoption)
				prevoption.after(option);
			else
			{
				for (let opt of Curriculums.elements.course.options)
					if (opt.value > course && (!prevoption || prevoption.value > opt.value))
						prevoption = opt;

				if (prevoption)
					prevoption.before(option)
				else
					Curriculums.elements.course.appendChild(option);
			}
		}

		Curriculums.elements.course.value = course;

		Curriculums.UpdateSubjectsFilter();
	}

	static ExportCourse()
	{
		let c = Curriculums.course;
		let data = Curriculums.GetExportCourseData();

		Curriculums.graphs[c] = data.graph;
		Curriculums.budget[c] = data.budget;

		for (let [id, subject] of Object.entries(data.subjects))
		{
			if (!Curriculums.subjects[id])
				Curriculums.subjects[id] = {subject: null, courses: {}};

			Curriculums.subjects[id].subject = subject.subject;

			if (subject.course)
				Curriculums.subjects[id].courses[c] = subject.course;
			else if (Curriculums.subjects[id].courses[c])
				delete Curriculums.subjects[id].courses[c];
		}

		Curriculums.ClearEmptySubjects();
	}

	static GetExportCourseData()
	{
		let data = {
			graph: Curriculums.graph.GetData()[0],
			budget: {},
			subjects: {},
		};

		for (let item of Curriculums.elements.budgettable.Items)
			if (item.Item.Value)
				data.budget[item.Item.Value] = {
					activity: {id: item.Item.Value, name: item.Item.Name, fullname: item.Item.FullName},
					length: parseFloat(item.Row.Weeks.value),
				}

		for (let item of Curriculums.elements.subjects.Items)
		{
			let subjectdata = {
				subject: item.Item.Value ? {id: item.Item.Value, name: item.Item.Name, fullname: item.Item.FullName} : null,
				course: {},
			};

			for (let [s, semester] of Object.entries(item.Row.Semesters))
			{
				let semdata = {
					hours: parseInt(semester.Hours.value),
					whours: parseInt(semester.WHours.value),
					lphours: parseInt(semester.LPHours.value),
					cdhours: parseInt(semester.CDHours.value),
					exam: semester.Exam.checked ? 1 : 0,
				};

				if (!isNaN(semdata.hours) && semdata.hours > 0)
				{
					if (isNaN(semdata.lphours)) semdata.lphours = 0;
					if (isNaN(semdata.cdhours)) semdata.cdhours = 0;

					subjectdata.course[s] = semdata;
				}
			}

			if (Object.keys(subjectdata.course).length == 0)
				subjectdata.course = null;

			if (item.Item.SubjectID == null)
			{
				item.Item.SubjectID = 0;
				while (Curriculums.subjects[item.Item.SubjectID] || data.subjects[item.Item.SubjectID]) item.Item.SubjectID++;
			}

			data.subjects[item.Item.SubjectID] = subjectdata;
		}

		return data;
	}

	static validateCourse(silent)
	{
		for (let item of Curriculums.elements.budgettable.Items)
			if (
				silent ? !item.Item.Value || !item.Row.Weeks.checkValidity() :
				!item.Item.reportValidity() || !item.Row.Weeks.reportValidity()
			) return false;

		for (let item of Curriculums.elements.subjects.Items)
		{
			if (silent ? !item.Item.Value : !item.Item.reportValidity())
				return false;

			for (let [s, semester] of Object.entries(item.Row.Semesters))
			{
				if (
					silent ? 
					!semester.Hours.checkValidity() ||
					!semester.WHours.checkValidity() ||
					!semester.LPHours.checkValidity() ||
					!semester.CDHours.checkValidity() :
					!semester.Hours.reportValidity() ||
					!semester.WHours.reportValidity() ||
					!semester.LPHours.reportValidity() ||
					!semester.CDHours.reportValidity()
				) return false;

				let hours = parseInt(semester.Hours.value);
				if (!isNaN(hours) && hours > 0)
				{
					let whours = parseInt(semester.WHours.value);
					if (isNaN(whours) || whours == 0)
					{
						if (!silent) Shared.DisplayMessage(`Предмет ${item.Item.FullName} имеет некорректное количество часов в неделю в семестре ${s}`, "error");
						return false;
					}
				}
			}
		}

		return true;
	}

	static LoadCurriculum(data)
	{
		Curriculums.CurriculumID = data?.id?.id;

		if (Curriculums.CurriculumID)
			Shared.SetCookie("editor-curriculum-id", Curriculums.CurriculumID);
		
		if (Curriculums.elements.delete)
			Curriculums.elements.delete.disabled = Curriculums.elements.copy.disabled = !Curriculums.CurriculumID;

		Curriculums.elements.name.value = data?.name ?? "";
		Curriculums.qualification.SetValue(data?.qualification);
		Curriculums.educationlevel.SetValue(data?.educationlevel);

		if (data) Curriculums.elements.year.value = data.year;

		Curriculums.elements.course2.checked = data?.course2 ?? false;

		Curriculums.graphs = {};
		Curriculums.budget = {};
		Curriculums.subjects = {};

		Curriculums.elements.course.innerHTML = "";

		let course = null;

		if (data)
		{
			for (let [c, graph] of Object.entries(data.graphs))
			{
				if (course == null) course = c;
				Curriculums.graphs[c] = graph;

				Curriculums.budget[c] = {};
				for (let activity of graph)
				{
					let id = activity.activity.id;
					
					if (!Curriculums.budget[c][id])
						Curriculums.budget[c][id] = {
							activity: activity.activity,
							length: 0,
						};

					Curriculums.budget[c][id].length += activity.length;
				}

				let option = document.createElement("option");
				option.value = c;
				option.innerText = c;
				Curriculums.elements.course.appendChild(option);
			}

			for (let [id, subject] of data.subjects.entries())
				Curriculums.subjects[id] = subject;
		}

		Curriculums.course = null;
		Curriculums.SwitchCourse(course);

		Curriculums.saved = true;
	}

	static OpenCurriculum(id)
	{
		Curriculums.lastmsg?.remove();
		let info = Shared.DisplayMessage(`Загружается учебный план...`, "warning");

		Shared.RequestGET("curriculum", {
			id: id,
			actdate: Header.GetActualDate(),
		}, function(data) {
			Curriculums.LoadCurriculum(data);
			Curriculums.saved = true;

			Curriculums.lastmsg?.remove();
			Curriculums.lastmsg = Shared.DisplayMessage(`Загружен учебный план ${data.id.name}`, "success");
		}, function (status, code, message) {
			Curriculums.lastmsg?.remove();
			Curriculums.lastmsg = Shared.DisplayMessage(`Не удалось загрузить учебный план (${message})`, "error");
		}, function (status, message) {
			Curriculums.lastmsg?.remove();
			Curriculums.lastmsg = Shared.DisplayMessage(`Не удалось загрузить учебный план (Ошибка HTTP ${status}: ${message})`, "error");
		}, function (status, message) {
			info.remove();
		});
	}

	static SaveCurriculum(silent)
	{
		let info = null;
		let data = Curriculums.GetCurriculum();

		if (!silent)
		{
			Curriculums.lastmsg?.remove();
			info = Shared.DisplayMessage(`Учебный план ${data.name} сохраняется...`, "warning");
		}

		Curriculums.elements.save.disabled = true;

		Shared.RequestPOST("curriculum", data, {
			type: "save",
			actdate: Header.GetActualDate(),
		}, function(data) {
			Curriculums.CurriculumID = data.id;
			Curriculums.saved = true;
			Curriculums.elements.delete.disabled = false;

			Shared.SetCookie("editor-curriculum-id", data.id);

			if (!silent)
			{
				Curriculums.lastmsg?.remove();
				Shared.DisplayMessage(`Учебный план ${data.name} успешно сохранен`, "success");
			}
		}, !silent ? function (status, code, message) {
			Curriculums.lastmsg?.remove();
			Curriculums.lastmsg = Shared.DisplayMessage(`Не удалось сохранить учебный план (${message})`, "error");
		} : null, !silent ? function (status, message) {
			Curriculums.lastmsg?.remove();
			Curriculums.lastmsg = Shared.DisplayMessage(`Не удалось сохранить учебный план (Ошибка HTTP ${status}: ${message})`, "error");
		} : null, function (status, message) {
			if (!silent) info.remove();

			Curriculums.elements.save.disabled = false;
		});
	}

	static validateCurriculum(silent)
	{
		if (!(silent ? Curriculums.elements.name.checkValidity() : Curriculums.elements.name.reportValidity())) return false;
		if (!(silent ? Curriculums.elements.year.checkValidity() : Curriculums.elements.year.reportValidity())) return false;
		if (!(silent ? Curriculums.qualification.Value : Curriculums.qualification.reportValidity())) return false;
		if (!(silent ? Curriculums.educationlevel.Value : Curriculums.educationlevel.reportValidity())) return false;
		if (!Curriculums.validateCourse(silent)) return false;

		Curriculums.ExportCourse();
		Curriculums.ClearEmptySubjects();

		for (let [c, graph] of Object.entries(Curriculums.graphs))
		{
			let budget = Curriculums.budget[c];
			if (!budget)
			{
				if (!silent) Shared.DisplayMessage(`Некорректный бюджет времени для курса ${c}`, "error");
				return false;
			}

			let lengths = {};
			for (let act of graph)
			{
				if (!budget[act.activity.id])
				{
					if (!silent) Shared.DisplayMessage(`В бюджете времени курса ${c} не обнаружена деятельность ${act.activity.fullname}, используемая в графике`, "error");
					return false;
				}

				lengths[act.activity.id] = (lengths[act.activity.id] ?? 0) + act.length;
			}
			
			for (let [id, act] of Object.entries(budget))
			{
				let blen = act.length;
				let alen = lengths[id] ?? 0;

				if (Math.abs(alen - blen) > 0.09)
				{
					if (!silent) Shared.DisplayMessage(`Длительность деятельности ${act.activity.fullname} графика курса ${c} не соответствует бюджету (график: ${Math.round(alen * 10) / 10}, бюджет: ${blen})`, "error");
					return false;
				}
			}
		}

		return true;
	}

	static GetCurriculum()
	{
		let subjects = [];

		for (let subject of Object.values(Curriculums.subjects))
			if (subject.subject != null) subjects.push(subject);

		return {
			id: Curriculums.CurriculumID ? {id: Curriculums.CurriculumID} : null,
			year: Curriculums.elements.year.value,
			educationlevel: {id: Curriculums.educationlevel.Value},
			qualification: {id: Curriculums.qualification.Value},
			name: Curriculums.elements.name.value,
			course2: Curriculums.elements.course2.checked ? 1 : 0,
			graphs: Curriculums.graphs,
			subjects: subjects,
		};
	}

	static UpdateSubjectsFilter()
	{
		let value = Curriculums.elements.filter.value.toLowerCase();

		for (let item of Curriculums.elements.subjects.Items)
		{
			item.Row.hidden = false;

			if (value && (!item.Item.Value || item.Item.Name.toLowerCase().indexOf(value) < 0 && item.Item.FullName.toLowerCase().indexOf(value) < 0))
				item.Row.hidden = true;
		}
	}

	static ClearEmptySubjects()
	{
		for (let id of Object.keys(Curriculums.subjects))
		{
			let del = true;

			for (let course of Object.values(Curriculums.subjects[id].courses))
				if (course != null) { del = false; break; }

			if (del) delete Curriculums.subjects[id];
		}
	}

	static UpdateAutoSaveTimer()
	{
		if (Curriculums.autosave) clearInterval(Curriculums.autosave);

		Curriculums.autosave = setInterval(function() {
			if (Curriculums.saved || !Curriculums.validateCurriculum(true)) return;

			Curriculums.SaveCurriculum(true);
		}, 1000 * 60);
	}

	static Initialize()
	{
		Curriculums.qualification.id = "editor-info-qualification";
		Curriculums.qualification.SetRequired(true);
		Curriculums.elements.qualification.appendChild(Curriculums.qualification);

		Curriculums.educationlevel.id = "editor-info-educationlevel";
		Curriculums.educationlevel.SetRequired(true);
		Curriculums.elements.educationlevel.appendChild(Curriculums.educationlevel);

		Curriculums.graph.SetParent(Curriculums.elements.graph);
		Curriculums.graph.AddCustomColumn("Курс");

		let year = Curriculums.validateYear();
		if (year)
		{
			Curriculums.graph.SetYear(year);
			Curriculums.graph.SetupTable();
		}

		let budgettable = Curriculums.elements.budgettable;
		let budgetdesign = Curriculums.elements.budgetdesign;
		let subjects = Curriculums.elements.subjects;

		budgettable.NewActivityItem = function()
		{
			let item = Shared.ForeignKeyElement("Activities", "ID", null, [0, function() {
				let activities = [];

				for (let act of budgettable.Items)
					if (act.Item.Value)
						activities.push(act.Item.Value);

				return activities;
			}]);
			item.SetRequired(true);

			return item;
		}

		Shared.MakeTableAsItemList(budgettable, 3, 0, function() {
			let item = budgettable.NewActivityItem();
			item.click();

			return item;
		}, function(row, grid, item) {
			row.Weeks = document.createElement("input");
			row.Weeks.type = "number";
			row.Weeks.step = 0.1;
			row.Weeks.min = 0.1;
			row.Weeks.max = 60;
			row.Weeks.value = row.Weeks.min;
			row.cells[1].appendChild(row.Weeks);

			if (item.Weeks) row.Weeks.value = item.Weeks;

			let length = document.createElement("input");
			length.type = "text";
			length.disabled = true;
			row.cells[2].appendChild(length);

			length.Update = function()
			{
				let value = parseFloat(row.Weeks.value);

				if (value)
				{
					let weeks = Math.floor(value);
					let days = Math.round(value % 1 * 7);

					length.value = `${weeks} недель + ${days} дней`;
				}
				else
					length.value = "";
			}

			row.Weeks.addEventListener("change", function() {
				length.Update();
			});
			length.Update();

			item.addEventListener("change", function(event) {
				budgetdesign.Update();
			});
		});

		subjects.NewSubjectItem = function()
		{
			let item = Shared.ForeignKeyElement("Subjects", "ID", null, [0, function() {
				let subjs = [];

				for (let subj of subjects.Items)
					if (subj.Item.Value)
						subjs.push(subj.Item.Value);

				return subjs;
			}]);
			item.SetRequired(true);

			return item;
		}

		Shared.MakeTableAsItemList(subjects, 11, 0, function() {
			let item = subjects.NewSubjectItem();
			item.click();

			return item;
		}, function(row, grid, item) {
			row.Semesters = {};

			for (let semester = 1; semester <= 2; semester++)
			{
				let hours = document.createElement("input");
				hours.type = "number";
				hours.min = 0;
				row.cells[1 + (semester - 1) * 5].appendChild(hours);

				let whours = document.createElement("input");
				whours.type = "number";
				whours.min = 0;
				row.cells[2 + (semester - 1) * 5].appendChild(whours);

				let lphours = document.createElement("input");
				lphours.type = "number";
				lphours.min = 0;
				row.cells[3 + (semester - 1) * 5].appendChild(lphours);

				let cdhours = document.createElement("input");
				cdhours.type = "number";
				cdhours.min = 0;
				row.cells[4 + (semester - 1) * 5].appendChild(cdhours);

				let exam = document.createElement("input");
				exam.type = "checkbox";
				row.cells[5 + (semester - 1) * 5].appendChild(exam);

				row.Semesters[semester] = {
					Hours: hours,
					WHours: whours,
					LPHours: lphours,
					CDHours: cdhours,
					Exam: exam,
				};

				let isem = item.Semesters && item.Semesters[semester];
				if (isem)
				{
					hours.value = isem.hours;
					whours.value = isem.whours;
					lphours.value = isem.lphours;
					cdhours.value = isem.cdhours;
					exam.checked = isem.exam != 0;
				}
			}
		}, function(row, grid, item) {
			if (item?.SubjectID != null) delete Curriculums.subjects[item.SubjectID];
		});

		Curriculums.elements.course.addEventListener("change", function(event) {
			if (Curriculums.validateCourse())
				Curriculums.SwitchCourse(Curriculums.elements.course.value);
			else
				Curriculums.elements.course.value = Curriculums.course;
		});

		Curriculums.elements.editorinfo.addEventListener("change", function(event) {
			if (event.target == Curriculums.elements.course) return;

			Curriculums.saved = false;
		});

		Curriculums.elements.budgettable.addEventListener("change", function(event) {
			Curriculums.saved = false;
		});

		Curriculums.elements.graph.addEventListener("change", function(event) {
			Curriculums.saved = false;
		});

		Curriculums.elements.subjects.addEventListener("change", function(event) {
			if (event.target == Curriculums.elements.filter) return;

			Curriculums.saved = false;
		});

		Curriculums.elements.open.addEventListener("click", function(event) {
			if (!Curriculums.saved && !Shared.UnsavedConfirm()) return;

			Shared.SelectForeignKey("Curriculums", function(row) {
				if (!row) return;

				let id = row.Cells.ID.Input.GetDataValue();
				Curriculums.OpenCurriculum(id);
			});
		});

		Curriculums.elements.add?.addEventListener("click", function(event) {
			if (!Curriculums.validateCourse()) return;

			let dialog = Shared.CreateDialog("Введите номер нового курса");

			let course = document.createElement("input");
			course.className = "new-course-input";
			course.type = "number";
			course.min = 1;
			course.max = 10;
			course.value = 1;
			course.required = true;

			while (Curriculums.graphs[course.value] || Curriculums.course == course.value)
				course.value++;

			dialog.Body.appendChild(course);

			dialog.AddOption("Добавить", function(event) {
				if (!course.reportValidity()) return;

				if (Curriculums.course != course.value)
					Curriculums.SwitchCourse(course.value);

				dialog.remove();
			});

			dialog.SetWidth(250);
			dialog.SetHeight(30);
			dialog.Center();
		});

		Curriculums.elements.remove?.addEventListener("click", function(event) {
			let course = Curriculums.course;

			if (!confirm(`Вы уверены, что хотите удалить данные по курсу ${course}?`)) return;

			delete Curriculums.graphs[course];

			for (let subject of Object.values(Curriculums.subjects))
				if (subject[course]) delete subject[course];

			Curriculums.ClearEmptySubjects();

			for (let option of Curriculums.elements.course.options)
				if (option.value == course) option.remove();

			Curriculums.course = null;

			if (Curriculums.graphs[course - 1])
				Curriculums.SwitchCourse(course - 1);
			else
				Curriculums.SwitchCourse(Object.keys(Curriculums.graphs)[0]);
		});

		Curriculums.elements.clear?.addEventListener("click", function(event) {
			if (!Curriculums.saved && !Shared.UnsavedConfirm()) return;

			Curriculums.LoadCurriculum();
		});

		Curriculums.elements.copy?.addEventListener("click", function(event) {
			if (!Curriculums.saved && !Shared.UnsavedConfirm()) return;
			if (!Curriculums.CurriculumID || !confirm("Данный учебный план сохранён. Создать новый учебный план как копию текущего?")) return;

			Curriculums.CurriculumID = null;
			Curriculums.elements.copy.disabled = Curriculums.elements.delete.disabled = true;
		});

		Curriculums.elements.save?.addEventListener("click", function(event) {
			if (!Curriculums.validateCurriculum()) return;
			if (!confirm("Сохранить текущий учебный план?")) return;

			Curriculums.SaveCurriculum();
			Curriculums.UpdateAutoSaveTimer();
		});

		Curriculums.elements.delete?.addEventListener("click", function(event) {
			if (!Curriculums.CurriculumID || !confirm("Вы уверены, что хотите удалить данный учебный план?")) return;

			Curriculums.lastmsg?.remove();
			let info = Shared.DisplayMessage(`Учебный план удаляется...`, "warning");

			Shared.RequestPOST("curriculum", {}, {
				id: Curriculums.CurriculumID,
				type: "delete",
				actdate: Header.GetActualDate(),
			}, function(data) {
				Curriculums.LoadCurriculum();

				Curriculums.lastmsg?.remove();
				Shared.DisplayMessage(`Учебный план успешно удален`, "success");
			}, function (status, code, message) {
				Curriculums.lastmsg?.remove();
				Curriculums.lastmsg = Shared.DisplayMessage(`Не удалось удалить учебный план (${message})`, "error");
			}, function (status, message) {
				Curriculums.lastmsg?.remove();
				Curriculums.lastmsg = Shared.DisplayMessage(`Не удалось удалить учебный план (Ошибка HTTP ${status}: ${message})`, "error");
			}, function (status, message) {
				info.remove();
			});
		});

		Curriculums.elements.year.addEventListener("change", function(event) {
			let year = Curriculums.validateYear();
			if (year)
			{
				Curriculums.graph.SetYear(Curriculums.GetCourseYear());
				Curriculums.graph.SetupTable();
			}
		});

		Curriculums.elements.course2.addEventListener("change", function(event) {
			let year = Curriculums.validateYear();
			if (year)
			{
				Curriculums.graph.SetYear(Curriculums.GetCourseYear());
				Curriculums.graph.SetupTable();
			}
		});

		Curriculums.elements.filter.addEventListener("input", function(event) {
			Curriculums.UpdateSubjectsFilter();
		});

		budgetdesign.Update = function()
		{
			budgetdesign.tBodies[0].innerHTML = "";

			for (let item of budgettable.Items)
			{
				if (!item.Item.Value) continue;

				let design = document.createElement("tr");
				budgetdesign.tBodies[0].appendChild(design);
				
				let fullname = document.createElement("td");
				fullname.innerText = item.Item.FullName;
				design.appendChild(fullname);

				let name = document.createElement("td");
				name.innerText = item.Item.Name;
				design.appendChild(name);
			}
		}

		onbeforeunload = function(event)
		{
			if (!Curriculums.saved)
				return "Учебный план не сохранён";
		}

		Curriculums.LoadCurriculum();

		if (Shared.GetCookie("editor-curriculum-id"))
			Curriculums.OpenCurriculum(Shared.GetCookie("editor-curriculum-id"));

		Curriculums.UpdateAutoSaveTimer();

		if (Curriculums.elements.save)
			window.addEventListener("keydown", function(event) {
				if (!event.ctrlKey || event.code != "KeyS") return;

				event.preventDefault();

				if (!Curriculums.elements.save.disabled)
					Curriculums.elements.save.click();
			});
	}
}
Curriculums.Initialize();