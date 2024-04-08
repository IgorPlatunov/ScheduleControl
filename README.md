# Информационная система расписания колледжа

Автоматизирует и упрощает работу с расписанием занятий и связанных с ним объектов в учебных заведениях. Предоставляет возможность работы с учебными планами, графиками образовательного процесса, нагрузками групп и преподавателей, расписаниями на семестр и ежедневными расписаниями занятий. Контролирует занятость преподавателей и кабинетов при составлении расписаний. Имеет механизм автоматического составления расписания по данным вычитки групп, занятости и другой информации.

## Развёртывание

Поставляется как веб-приложение. Требуется веб-сервер с модулями PHP 8.1 или выше и MySQL 8.0 или выше. Проект (как директория **schedulecontrol**) заливается на веб-сервер в любую директорию относительно директории ROOT веб-сервера. Название директории с файлами проекта может быть изменено, если необходимо.

Перед первым запуском требуется настроить конфигурацию **php/schedulecontrol/config.ini**, где нужно указать данные для подключения к БД. Запуск возможен по переходу по ссылке к корневой директории проекта в браузере.

Для быстрого развёртывания и просмотра можно использовать готовую базу данных, создаваемую через файл с запросами **ScheduleControlTest.sql**. Выполнение запросов этого файла создаст базу данных **ScheduleControlTest**. Для её использования задайте соответствующее название используемой базы данных в конфигурационном файле системы.
