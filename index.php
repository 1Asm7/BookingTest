<?php
// Параметры подключения к базе данных
define("SERVERNAME", "localhost");
define("USERNAME", "username");
define("PASSWORD", "password");
define("DBNAME", "database");

/**
 * Генерирует уникальный штрих-код
 *
 * @param int $length Длина штрих-кода
 
 * @return string Сгенерированный штрих-код
 */
function generateBarcode($length) {
    return substr(str_shuffle(str_repeat('0123456789', $length)), 0, $length);
}

/**
 * Создает заказ в БД после успешного подтверждения бронирования билетов
 *
 * @param int $event_id ID события
 * @param string $event_date Дата и время на которое были куплены билеты
 * @param int $ticket_adult_price Цена взрослого билета на момент покупки
 * @param int $ticket_adult_quantity Количество купленных взрослых билетов в этом заказе
 * @param int $ticket_kid_price Цена детского билета на момент покупки
 * @param int $ticket_kid_quantity Количество купленных детских билетов в этом заказе
 *
 * @return void
 */
function addNewOrder($event_id, $event_date, $ticket_adult_price, $ticket_adult_quantity, $ticket_kid_price, $ticket_kid_quantity) {
	$clientAPI = new GuzzleHttp\Client(); // клиент для API-запросов
	$conn = new mysqli(SERVERNAME, USERNAME, PASSWORD, DBNAME);
	if ($conn->connect_error) {
		die("Connection failed: " . $conn->connect_error);
	}
	
	while (true) {
		// Генерация barcode
		$barcode = generateBarcode(10);
		
		// Проверка в БД
		$sql = "SELECT barcode FROM orders WHERE barcode=" . $barcode;
		$result = $conn->query($sql);

		if ($result->num_rows > 0) {
			// Ошибка, barcode уже есть в базе
			continue;
		}
		
		// Бронь заказа
		$res = $clientAPI->request('GET', 'https://api.site.com/book', [
			'event_id' => $event_id, 
			'event_date' => $event_date, 
			'ticket_adult_price' => $ticket_adult_price, 
			'ticket_adult_quantity' => $ticket_adult_quantity, 
			'ticket_kid_price' => $ticket_kid_price, 
			'ticket_kid_quantity' => $ticket_kid_quantity, 
			'barcode' => $barcode
		]);
		$res_code = $res->getStatusCode();
		if ($res_code == 200 or $res_code == 204) {
			$res_json = json_decode($response->getBody());
			if (isset($res_json['message']) && $res_json['message'] == 'order successfully booked') {
				break;
			}
		} else {
			echo 'Ошибка бронирования! Повторите позже';
			break;
		}
	}
	
	// Подтверждение заказа
	$res = $clientAPI->request('GET', 'https://api.site.com/approve', [
		'barcode' => $barcode
	]);
	$res_code = $res->getStatusCode();
	if ($res_code == 200 or $res_code == 204) {
		$res_json = json_decode($response->getBody());
		if (isset($res_json['message']) && $res_json['message'] == 'order successfully aproved') {
			// Считаем общую сумму
			$equal_price = $ticket_adult_price * $ticket_adult_quantity + $ticket_kid_price * ticket_kid_quantity;
			
			// Сохраняем заказ в БД
			$stmt = $conn->prepare("INSERT INTO orders (event_id, event_date, ticket_adult_price, ticket_adult_quantity, ticket_kid_price, ticket_kid_quantity, barcode, equal_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
			$stmt->bind_param("isiiiisi", $event_id, $event_date, $ticket_adult_price, $ticket_adult_quantity, $ticket_kid_price, $ticket_kid_quantity, $barcode, $equal_price);
			if ($stmt->execute()) {
				echo "Заказ успешно подтвержден.";
			} else {
				echo "Error SQL: " . $stmt->error;
			}
		} else {
			if (isset($res_json['error'])) {
				$error_msg = $res_json['error'];
			} else {
				$error_msg = '';
			}
			echo 'Ошибка подтверждения! ' . $error_msg;
		}
	} else {
		echo 'Ошибка подтверждения! Повторите позже';
	}
	
	$conn->close();
}

?>