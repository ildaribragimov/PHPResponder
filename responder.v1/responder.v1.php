<?php
// Отправка HTTP заголовка серверу о том, что далее будет передан HTML-документ в кодировке UTF-8
header("Content-type: text/html; charset=UTF-8 ");



/**
 * Параметры подключния к БД
 */
$host		= 'localhost';		// Хост
$username	= 'sbu16_scorsys';	// Имя пользователя БД
$pass		= 'TKjlNErhgS';		// Пароль доступа к БД
$dbname		= 'sbu16_scorsys';	// Имя БД
$prefix		= 'scorsys15_';		// Префикс таблиц базы данных

/**
 * Параметры установленного подключения к БД
 */
$dbConnect	= '';	// Последнее соединение
$selectedDb	= '';	// Выбранная для работы БД

/**
 * Параметры работы скрипта рассылки писем
 */
$log 				= array();			// Лог ошибок
$generatorString		= '';			// Считанные из файла данный для генератора случайного текста для файла
$logFileName			= 'responder.log';	// Файл в который записываем логи
$logFileMaxSize			= 5*1024;		// Максимальный размер лог файла в мегабайтах
$maxMailSend			= 100;			// Кол-во отправляемых писем за одно срабатывание скрипта
$maxMailSendInDay		= 1900;			// Максимальное количество отправляемых писем в сутки
$countMailSend			= 0;			// Общий счетчик отправленных писем
$updateSubscriberInfo	= array();		// Массив запросов на обновление списка отправленных материалов подписчику
$timeout				= 2;			// Задержка в выполнении функции отправки писем в секундах
$localhostPath			= '/home/s/sbu16/responder.v1/'; // Путь к директории файлов рассылщика



/**
 * Функция создает файл
 *
 * Параметры:
 * * $filename (тип: string) - Имя файла, который необходимо создать
 * * $fileContent (тип: string) - Содержимое, которое необходимо записать в файл
 */
function createFile($filename, $fileContent) {
	// Явное указание на использование глобальных переменных
	global $localhostPath;
	// Создание файла
	$stF = fopen($localhostPath.$filename, "a");
	// Запись содержимого в файл
	fwrite($stF, $fileContent);
	// Закрытие файла, завершение записи
	fclose($stF);
	// Возврат положительного результата
	return true;
}



/**
 * Функция проверки наличия файла
 *
 * Параметры:
 * * $filename (тип: string) - Имя файла, который необходимо проверить на наличие
 * * $logMessage (тип: string) - Текст сообщения, в случае, если файл найден
 *
 * Возвращаемое значение:
 * * true/false (тип: boolean) - Да (Если файл не существует) / Нет (Если файл найден)
 */
function checkFileExists($filename, $logMessage) {
	// Явное указание на использование глобальных переменных
	global $localhostPath, $log;
	// Проверяем, существует ли файл "stop.txt" на сервере
	if ( file_exists($localhostPath.$filename) ) {
		// Добавляем сообщение об ошибке в массив лога ошибок
		$log[] = $logMessage;
		// Завершение выполнения работы скрипта. Возврат отрицательного результата
		return true;
	}
	// Возврат положительного результата
	return false;
}



/**
 * Функция открывает подклчение с БД
 *
 * Возвращаемое значение:
 * * true/false (тип: boolean) - Соединение установлено/не установлено
 */
function dbConnect() {
	// Явное указание на использование глобальных переменных
	global $dbConnect, $dbname, $selectedDb, $host, $username, $pass, $log;
	// Открытие соединения с сервером БД
	$dbConnect = mysql_connect($host, $username, $pass);
	// Если операция открытия соединения с сервером MySQL вернула сообщение об ошибке (не пустую строку)
	if ( !$dbConnect ) {
		// Добавление сообщения об ошибке в массив лога
		$log[] = date('d.m.Y H:i:s').": MySQL connect error: ".mysql_error();
		// Возвращаем отрицательный результат выполнения функции
		return false;
	}
	// Установка кодировки для текущего соединения с БД
	mysql_set_charset('utf8', $dbConnect);
	// Выбираем БД
	$selectedDb = mysql_select_db($dbname, $dbConnect);
	// Если операция выбола БД MySQL вернула сообщение об ошибке (не пустую строку)
	if ( !$selectedDb ) {
		// Добавление сообщения об ошибке в массив лога
		$log[] = date('d.m.Y H:i:s').": MySQL connect error: ".mysql_error();
		// Возвращаем отрицательный результат выполнения функции
		return false;
	}
	// Возвращаем положительный результат выполнения функции 
	return true;
}



/**
 * Функция получает параметры компонента
 *
 * Возвращаемое значние:
 * * $parameters (тип: array) - Ассоциативный массив параметров
 */
function getParams() {
	// Явное указание на использование глобальных переменных
	global $dbConnect, $prefix, $log;
	// Формируем запрос на получение параметров компонента рассылки
	$query = "SELECT `params` FROM `".$prefix."components` WHERE `option`='com_subscription' AND `parent`=0";
	// Посылаем запрос в БД и получаем его результат
	$result = mysql_query($query, $dbConnect);
	// Если запрос в БД вернул ошибку
	if ( !$result ) {
		// Добавление сообщения об ошибке в массив лога
		$log[] = date('d.m.Y H:i:s').": MySQL connect error: ".mysql_error();
		// Возвращаем отрицательный результат выполнения функции
		return false;
	}
	// Получение первого поля первой строки результата
	$params = mysql_result( $result, 0, 0 );
	// Преобразование строки "$params" в массив. Выделение каждой строки результата поля параметров в отдельный элемент массива
	$params = explode("\n", $params);
	// Обход массива в цикле
	foreach ( $params as $value ) {
		// Разбиание строки параметра на массив, содержажий "ключ" => "значение"
		$values = explode('=', $value);
		// Генерация массива параметров "$parameter"
		$parameters[$values[0]] = $values[1];
	}
	// Возвращаем ассоциативный массив параметров компонента
	return $parameters;
}



/**
 * Функция переопределяет параметры скрипта
 */
function setParams() {
	// Явное указание на использование глобальных переменных
	global $maxMailSend, $logFileMaxSize;
	// Получение параметров компонента
	$parameters = getParams();
	// Если запрос в БД вернул ошибку
	if ( empty($parameters) || $parameters === false ) {
		// Возвращаем отрицательный результат выполнения функции
		return false;
	}
	// Переопределение значений параметра скрипта значениями компонента
	$maxMailSend = ( $parameters['mailSendKolLimit'] != '' && (int)$parameters['mailSendKolLimit'] > 0 )
		? $parameters['mailSendKolLimit']
		: $maxMailSend;
	// Переопределение значений параметра скрипта значениями компонента
	$logFileMaxSize = ( $parameters['logFileMaxSize'] != '' && (int)$parameters['logFileMaxSize'] > 1 )
		? 1024*1024*$parameters['logFileMaxSize']
		: $logFileMaxSize;
	// Возвращаем положительный результат выполнения функции
	return true;
}



/**
 * Функция получает параметры первой активной рассылки
 *
 * Возвращаемое значние:
 * * $mailingGroup (тип: array) - Ассоциативный массив первой активной рассылки
 */
function getMailingGroup() {
	// Явное указание на использование глобальных переменных
	global $dbConnect, $prefix, $log;
	// Формируем запрос на выборку активной почтовой рассылки
	$query = "SELECT * FROM `".$prefix."subscribers_emails` WHERE `published`=1 AND `categories`<>'' AND `textemail`<>'' ORDER BY `id` LIMIT 1";
	// Посылаем запрос в БД и получаем его результат
	$result = mysql_query($query, $dbConnect);
	// Если запрос в БД вернул ошибку
	if ( !$result ) {
		// Добавление сообщения об ошибке в массив лога
		$log[] = date('d.m.Y H:i:s').": MySQL connect error: ".mysql_error();
		// Возвращаем отрицательный результат выполнения функции
		return false;
	}
	// Получение результата запроса в виде ассоциативного массива
	$mailingGroup = mysql_fetch_assoc($result);
	// Возвращаем ассоциативный массив первой активной рассылки
	return $mailingGroup;
}



/**
 * Функция получает количество отправленных писем из отчера за предыдущий день
 *
 * Возвращаемое значение:
 * * $data (тип: string) - Содержимое файла "dayReport.txt"
 */
function getYesterdayReportData() {
	// Явное указание на использование глобальных переменных
	global $localhostPath;
	// Устанавливаем переменной результата функции значение по умолчанию
	$data = 0;
	// Проверяем, существует ли файл "dayReport.txt" на сервере
	if ( file_exists($localhostPath.'dayReport.txt') ) {
		// Читаем содержимое файла в строку
		$data = file_get_contents( $localhostPath.'dayReport.txt' );
	}
	// Завершение выполнения работы скрипта. Возврат отрицательного результата
	return (int)$data;
}

/**
 * Функция получает строку условий выборки
 *
 * Параметры:
 * * $categories (тип: array) - Массив категорий рассылки
 *
 * Возвращаемое значние:
 * * $catWhere (тип: string) - Строка условий выборки в соответсткии категориям материалов подписки, на которые подписался подписчик
 */
function getCatWhere($categories) {
	// Если массив категорий не пуст
	if ( !empty($categories) ) {
		// Генерация массива условий выборки в соответсткии категориям материалов подписки, на которые подписался подписчик
		for ( $i = 0; $i < count($categories); $i++ ) {
			// Если значение элемента массива не пустое
			if ( $categories[$i] != '' ) {
				// Добавляем к массиву условий WHERE новое условие
				$catWhere[] = " FIND_IN_SET('".$categories[$i]."', `categories`) ";
			}
		}
		// Преобразование массива условий WHERE в строку через объединяющую строку OR
		$catWhere = implode(' OR ', $catWhere);
		// Обертка условий выборки в соответствии с категориями материалов подписки
		$catWhere = " AND (".$catWhere.")";
	} else {
		$catWhere = '';
	}
	// Возвращаем строку условий выборки
	return $catWhere;
}



/**
 * Функция получает список подписчиков активной рассылки
 *
 * Параметры:
 * * $categories (тип: array) - Массив категорий рассылки
 * * $mailingGroupLog (тип: integer) - Количество уже отправленных писем
 *
 * Возвращаемое значние:
 * * $subscribers (тип: array) - Ассоциативный массив подписчиков
 */
function getSubscribers($categories, $mailingGroupLog) {
	// Явное указание на использование глобальных переменных
	global $dbConnect, $prefix, $maxMailSend, $log;
	// Получаем строку условий выборки в соответсткии категориям материалов подписки, на которые подписался подписчик
	$catWhere = getCatWhere($categories);
	// Формируем запрос на выборку подписчиков активной категории из БД в количестве не более, чем определено настройкой "maxMailSent" (исключая тех, кому уже отправили)
	$query = "SELECT `email`, `fio`, `id`, `downloadedDocs` FROM `".$prefix."subscribers` WHERE `published`=1 AND `email`<>'' ". $catWhere." ORDER BY `id` LIMIT ".$mailingGroupLog.",".$maxMailSend;
	// Отправка запроса в БД и получение результата
	$result = mysql_query($query, $dbConnect);
	// Если запрос в БД вернул ошибку
	if ( !$result ) {
		// Добавление сообщения об ошибке в массив лога
		$log[] = "getSubscribers(): MySQL connect error: ".mysql_error();
		// Возвращаем отрицательный результат выполнения функции
		return false;
	}
	// Получение результата запроса в виде ассоциативного массива
	while ($subscriber = mysql_fetch_assoc($result)) {
		$subscribers[] = $subscriber;
	}
	// Возвращаем ассоциативный массив подписчиков
	return $subscribers;
}



/**
 * Генератор случайного контента для рассылки
 *
 * Возвращаемое значние:
 * * $dopString (тип: string) - Строка случайно сгенерированного текста
 */
function generator() {
	// Явное указание на использование глобальных переменных
	global $localhostPath, $generatorString, $log;
	// Читаем содержимое файла и помещаем его в переменную (!!!метод возвращает массив строк файла!!!)
	$fileContent = file ( $localhostPath.'library.txt' );
	// Если файл невозможно прочесть
	if ( !$fileContent ) {
		// Добавляем сообщение об ошибке в массив лога ошибок
		$log[] = 'generator(): Не найден файл словаря для генерации случайной строки! Случайная строка не сгенерирована! Письмо не отправлено!';
		// Возвращаем отрицательный результат выполнения функции
		return false;
	} 
	// Если массив словаря пуст
	if ( $generatorString == '' ){
		// Преобразовываем массив в строку (Объединяем элементы массива в строку)
		$content =  implode ( " ", $fileContent );
		// Массив символов, которые необходимо заменить в полученном содержимом файла словаря
		$replace = array("\r\n","\r",'!','!!','!!!','?','??','???',';', '.....','....','...','..');
		// Замена символов (массива $replace) в содержимом словаря (массив $content) на точки
		$content = str_replace($replace,'.',$content);
		// Разбиваем строку на массив подстрок
		$generatorString = explode('.',$content);
		// Очищаем от пустых элементов
		$generatorString = array_diff($generatorString, array('', ' ', null));
	}
	// Создаем разметку блока с сгенерированной строкой
	$dopString = '<div style="background-color:white;color:white;height:5px;display:none;overflow:hidden;">';
	// Устанавливаем позицию счетчика итераций генерации в положение 0
	$j = 0;
	// Генерируем текст из не более, чем 10 значений массива словаря
	while ( $j < 10 ) {
		// Добавляем значение случайного элемента массива в к сгенерированной строке
		$dopString .= $generatorString[rand(0, count($generatorString)-1 )].'.';
		// Увеличиваем счетчик итераций на единицу
		$j++;
	}
	// Завершаем разметку блока с сгенерированной строкой
	$dopString .= "</div>";
	// Возвращаем сгенерированную строку
	return $dopString;
}



/**
 * Функция получает максимальный ID среди подписчиков
 *
 * Параметры:
 * * $categories (тип: array) - Массив категорий рассылки
 *
 * Возвращаемое значние:
 * * $maxId (тип: int) - Максимальный ID среди подписчиков
 */
function getMaxId($categories) {
	// Явное указание на использование глобальных переменных
	global $dbConnect, $prefix, $log;
	// Получаем строку условий выборки в соответсткии категориям материалов подписки, на которые подписался подписчик
	$catWhere = getCatWhere($categories);
	// Формируем запрос в БД на получение максимального id пописчика среди подписчиков
	$query = "SELECT MAX(`id`) FROM `".$prefix."subscribers` WHERE `published`=1 AND `email`<>'' ". $catWhere." ORDER BY `id`";
	// Отправка запроса в БД и получение результата
	$result = mysql_query($query, $dbConnect);
	// Если последняя операция MySQL вернула сообщение об ошибке (не пустую строку)
	if( !$result ) {
		// Добавленеи сообщения об ошибках операции в MySQL в массив лога ошибок
		$log[] = "MySQL query error: ".mysql_error().' ('.$query.')';
		// Возвращаем отрицательный результат выполнения функции
		return false;
	}
	// Получение результата
	$maxId = mysql_result( $result, 0, 0 );
	// Возврат максимального ID среди подписчиков
	return $maxId;
}



/**
 * Функция записывает в файл лог работы скрипта
 */
function writeToLog(){
	// Явное указание на использование глобальных переменных
	global $localhostPath, $logFileName, $logFileMaxSize, $log;
	// Если файл существует И размер файла превышает максимально допустимый размер
	if ( file_exists($localhostPath.$logFileName) && filesize($localhostPath.$logFileName) > $logFileMaxSize ) {
		// Удаляем файл
		unlink($localhostPath.$logFileName);
	}
	// Преобразовываем лог-массив в строку
	$logText = implode("\r\n", $log);
	// Добавляем разделитель лога операции отправки очередного письма
	$logText = $logText."\r\n ----------------------------------------------------------- \r\n";
	// Создание лог файла и запись в файл записей массива $log
	createFile($logFileName, $logText);
	// Возврат положительного результата
	return true;
}



/**
 * Функция отправляет письма
 */
function sendMail()	{
	// Явное указание на использование глобальных переменных
	global $localhostPath, $dbConnect, $prefix, $maxMailSend, $maxMailSendInDay, $log, $timeout;
	// Устанавливаем значение "по умолчанию" переменной статуса завершения функции
	$status = false;
	// Формирвоание текста сообщения о наличии файла "stop.txt"
	$stopFileExistlogMessage = date('d.m.Y H:i:s').": Выполнение предыдущей версии скрипта еще не завершено!";
	// Формирвоание текста сообщения о наличии файла "gotLimit.txt"
	$gotlimitFileExistlogMessage = date('d.m.Y H:i:s').": Достигнут суточный лимит отправляемых писем. Рассылку необходимо возобновить вручную через 24 часа!";
	// Если файла "gotLimit.txt" на сервере нет
	// Если файла "stop.txt" на сервере нет
	// Если соединение с БД установлено
	// И параметры скрипта переопределены
	// И максимальное количество отправляемых за раз писем не равно 0
	if ( !checkFileExists('gotLimit.txt', $gotlimitFileExistlogMessage) && !checkFileExists('stop.txt', $stopFileExistlogMessage) && dbConnect() && setParams() && $maxMailSend !== 0 ) {
		// Создаем файл "stop.txt"
		createFile('stop.txt', 'stop');
		// Если есть активные почтовые рассылки, получаем параметры первой активной рассылки
		if ( $mail = getMailingGroup() ) {
			// Добавляем сообщение о начале работы скрипта в массив лога
			$log[] = date('d.m.Y H:i:s').": Начало работы скрипта!";
			// Получение списка адресатов, кому были отправлены письма
			$whoSent = ( $mail['whoSent'] )
				? explode(',', $mail['whoSent'])
				: array();
			// Получаем массив категорий материалов рассылки
			$categories = ( $mail['categories'] )
				? array_diff( explode(',', $mail['categories']), array('', ' ', null) ) // Сразу же очищаем от пустых элементов
				: array();
			// Получаем количество отправленных писем из отчера за предыдущий день
			$sendedYesterday = getYesterdayReportData();
			// Определение количества отправленных писем
			$sendedInAllTime = ( mail['log'] > count($whoSent) ) 
				? mail['log']
				: count($whoSent);
			// Подсчитываем, сколько еще можно отправить писем за этот день, не превышая лимитов хостинга
			$needToSend = $maxMailSendInDay + $sendedYesterday - $sendedInAllTime;
			// Переопределение максимального количества отправляемых за раз писем
			$maxMailSend = ( $needToSend > $maxMailSend )
				? $maxMailSend
				: $needToSend;
			// Если не достигнут суточный лимит количества отправляемых писем
			if ( $needToSend > 0 ) {
				// Получение списка подписчиков активной рассылки
				$subscribers = getSubscribers($categories, $mail['log']);
				// Если массив подписчиков не пуст
				if ( !empty($subscribers) ) {
					// Закрываем текущее соединение с БД, во избежание зазрыва соединения по таймауту
					mysql_close($dbConnect);
					// Обход массива подписчиков
					foreach ( $subscribers as $subscriber ) {
						// Если количество отправленных писем меньше максимального количества отправляемых писем за раз
						// И подписчику еще не отправлялось письмо
						if ( $countMailSend < $maxMailSend && !in_array($subscriber['email'], $whoSent) ) {
							// Установка временного интервала между отправками писем в 10 секунд
							sleep($timeout);
							// Определение значения по умолчанию сгенерированной строки, добавляемой к телу письма
							$generatorStr = '';
							// Если параметр "Подключать словарь генерации случайного текста?" рассылки установлен в позицию "Да"
							if ( $mail['generator'] == 1 ){
								// Получение сгенерированной строки случайного текста
								$generator = generator();
								// Если строка не сгенерирована
								if ( !$generator ) {
									// Переход к следующей итерации цикла
									continue;
								}
								// Добавляем сгенерированную строчку случайного текста
								$generatorStr = $generator;
							}
							/* --------- Генерация письма -------- */
							// Тема письма
							$subject = $mail['subject'];
							// Формируем тело письма для отправки
							// Подставляем Имя пльзователя в тело в шаблон письма если есть маркер -{fio}-
							$body = str_replace('-{fio}-', $subscriber['fio'], $mail['textemail']);
							// Подставляем Email пльзователя в тело в шаблон письма если есть маркер -{fio}-
							$body = str_replace('-{email}-', $subscriber['email'], $body);
							// Добавление сгенерированной строки к телу письма
							$body .= $generatorStr;
							// Формируем заголовок письма
							$headers  = "MIME-Version: 1.0\r\n";
							$headers .= "Content-type: text/html; charset=utf-8\r\n";
							$headers .= "From: ".$mail['myemail']."\r\n"; 
							$headers .= "Reply-To: ".$mail['myemail']."\r\n";
							/* --------- Отправка письма -------- */
							// Отправка письма адресату
							$sendingMail = mail($subscriber['email'], $subject, $body, $headers);
							/* --------- Проверка результата операции отправки -------- */
							// Если письмо успешно отправлено
							if ( $sendingMail ) {
								// Добавляем E-mail адрес подписчика в массив адресатов, кому уже отправлены письма
								$whoSent[] = $subscriber['email'];
								// Формируем запрос на обновление списка отправленных у подписчика
								$updateSubscriberInfo[] = "(".$subscriber['id'].",'".mysql_real_escape_string($subscriber['downloadedDocs']."\r\n".$mail['id'].'.'.$mail['subject'].' ('.date('H:i:s d.m.y')).")')";
								$log[] = 'Письмо c id='.$mail['id'].' успешно отправлено адресату '.$subscriber['email'];
							} else {
								// Добавляем сообщение об ошибке в массив лога ошибок
								$log[]='Письмо c id='.$mail['id'].' не отправлено на е-mail '.$subscriber['email'];
							}
							// Удаление переменных
							unset ( $body, $subject, $header );
							// Увеличиваем общий счетчик отправленных писем на единицу
							$countMailSend++;						
						} else {
							// Переход к следующей итерации цикла
							continue;
						}
					}
					// Формируем запрос в БД на обновление информации о рассылке
					$query = "UPDATE `".$prefix."subscribers_emails` SET  `log`=".($mail['log']+$countMailSend).", `whoSent`='".implode(',',$whoSent)."', `noteMail`='".$mail['noteMail']."\r\n".date('H:i:s d.m.Y').":Отправлено всего на ".count($whoSent)." e-mail адресов' WHERE `id`=".$mail['id'];
				}
				// Иначе Если всем подписчикам была отправлена рассылка
				else {
					// Формируем запрос в БД на обновление информации о рассылке
					$query = "UPDATE `".$prefix."subscribers_emails` SET `log`=0, `published`=-1, `send_date`='".date('Y-m-d H:i:s')."', `whoSent`='".implode(',',$whoSent)."', `noteMail`='".$mail['noteMail']."\r\n".date('H:i:s d.m.Y').":Отправлено всего на ".count($whoSent)." e-mail адресов' WHERE `id`=".$mail['id'];
				}
				// Восстанавливаем отключенное ранее соединение с БД
				dbConnect();
				// Отправка запроса в БД и получение результата
				$resultToDb = mysql_query($query, $dbConnect);
				// Если последняя операция MySQL вернула сообщение об ошибке (не пустую строку)
				if( !$resultToDb ) {
					// Добавленеи сообщения об ошибках операции в MySQL в массив лога ошибок
					$log[] = "MySQL query error: ".mysql_error().' ('.$query.')';
				}

				// Если массив запросов на обновление списка отправленных материалов подписчику не пустой
				if ( $updateSubscriberInfo ) {
					// Преобразовываем массив в строку
					$updateSubscriberInfo = implode ( ',', $updateSubscriberInfo );
					// Формируем строку запроса в БД на внесение изменений в данные подписчиков в БД
					$updateSubscriberInfo = 'INSERT INTO `'.$prefix.'subscribers` (`id`, `downloadedDocs`) VALUES'.$updateSubscriberInfo.' ON DUPLICATE KEY UPDATE `downloadedDocs` = VALUES(`downloadedDocs`)';
					// Отправка запроса в БД и получение результата
					$res = mysql_query($updateSubscriberInfo, $dbConnect);
					if( !$res ) {
						// Добавленеи сообщения об ошибках операции в MySQL в массив лога ошибок
						$log[] = "MySQL query error: ".mysql_error().' ('.$query.')';
					}
				}
			}
			// Иначе
			else {
				// Добавленеи сообщение о достижении суточного лимита в массив лога ошибок
				$log[] = "Достигнут суточный лимит отправляемых писем. Рассылка будет возобновлена завтра!";
				// Проверяем, существует ли файл "dayReport.txt" на сервере
				if ( file_exists($localhostPath.'dayReport.txt') ) {
					// Удаление файла "dayReport.txt"
					unlink($localhostPath.'dayReport.txt');
				}
				// Вызываем функцию создания файла отчета об общем количестве отправленных писем по текущей рассылке
				createFile('dayReport.txt', count($whoSent));
				// Вызываем функцию создания файла 
				createFile('gotLimit.txt', 'true');
			}
			// Добавляем сообщение об окончании работы скрипта в массив лога
			$log[] = date('d.m.Y H:i:s').": Завершение работы скрипта!";
		} else {
			// Добавляем сообщение об окончании работы скрипта в массив лога
			$log[] = date('d.m.Y H:i:s').": Рассылка не ведется! Нет активных почтовых рассылок!";
		}
		// Закрываем текущее соединение с БД
		mysql_close($dbConnect);
		// Удаление файла "stop"
		unlink($localhostPath.'stop.txt');
		// Устанавливаем значение переменной статуса завершения функции
		$status = true;
	}
	// Закрываем текущее соединение с БД
	mysql_close($dbConnect);
	// Записываем логи в файл
	writeToLog();
	// Завершаем выполнение работы скрипта. Выходим из функции
	return $status;
}



// Вызов функции отправки рассылки
sendMail();
?>