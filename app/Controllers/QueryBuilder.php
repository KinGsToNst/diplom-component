<?php
namespace App\Controllers;
use Aura\SqlQuery\QueryFactory;
use \Tamtamchik\SimpleFlash\Flash;
use function Tamtamchik\SimpleFlash\flash;
use PDO;
class QueryBuilder{
    private $pdo;
private $queryFactory;
private $flash;

public function __construct(QueryFactory $queryFactory,PDO $pdo){

    $host = 'localhost'; // имя сервера базы данных
    $dbname = 'component'; // имя базы данных
    $username = 'root'; // имя пользователя базы данных
    $pass = 'root'; // пароль пользователя базы данных
    $this->pdo = $pdo;
   $this->queryFactory=$queryFactory;
    $this->flash = new Flash();
}

    public function getAll($table, $joinInformation = null, $joinSocial = null, $joinStatus = null): bool|array
    {
        $select = $this->queryFactory->newSelect();

        // Задаем все колонки для основной таблицы
        $select
            ->from($table)
            ->cols([
                "{$table}.*", // Все колонки основной таблицы
            ]);

        // Добавляем JOIN и необходимые колонки
        if ($joinInformation) {
            $select->join(
                'LEFT',
                'user_information',
                'users.id = user_information.user_id'
            )->cols([
                'user_information.job_title',
                'user_information.phone',
                'user_information.address',
                'user_information.image',
                'user_information.status_id',
            ]);
        }

        if ($joinSocial) {
            $select->join(
                'LEFT',
                'user_social',
                'users.id = user_social.user_id'
            )->cols([
                'user_social.vk',
                'user_social.telegram',
                'user_social.instagram',
            ]);
        }

        if ($joinStatus) {
            $select->join(
                'LEFT',
                'status',
                'status.id = user_information.status_id'
            )->cols([
                'status.status_name',
            ]);
        }

        // Подготавливаем и выполняем запрос
        $sth = $this->pdo->prepare($select->getStatement());
        $sth->execute();

        // Возвращаем результат
        return $sth->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function is_equal($user,$current_user): bool
    {
        //сравнение идентификатора пользователя в списке users $user["id"]  c текущей сессией $_SESSION['$user_auth'];
        if($user["id"]==$current_user){
            return true;
        }
        return false;
    }
    public function update($id, $data, $mainTable = null, $userInfoTable = null): bool
    {
        // Обновление основной таблицы
        if (!empty($mainTable)) {
            $update = $this->queryFactory->newUpdate();

            // Фильтруем данные, чтобы оставить только те, которые должны быть обновлены в основной таблице
            $mainTableData = array_filter($data, function($key) {
                return in_array($key, ['email', 'username', 'password']);
            }, ARRAY_FILTER_USE_KEY);

            $update
                ->table($mainTable)
                ->cols($mainTableData)
                ->where('id = :id')
                ->bindValue('id', $id);

            $sth = $this->pdo->prepare($update->getStatement());

            // выполнение с привязанными значениями
            $sth->execute($update->getBindValues());
        }

  //если таблица user_information не пусто
        if($userInfoTable!==null){

    // Удаление старого изображения, если оно есть
    if (array_key_exists('old_image', $data) && file_exists($data['old_image'])) {
        unlink($data['old_image']);
    }
    // Проверяем, существует ли связанная запись в таблице user_information для данного пользователя
    $userInfoExists = $this->pdo->query("SELECT COUNT(*) FROM $userInfoTable WHERE user_id = $id")->fetchColumn();

    // если запись не создана в user_information
    if ($userInfoExists == 0 && (!empty($data['job_title']) || !empty($data['phone']) || !empty($data['address']) || isset($data['status_id']))) {
        $insertUserInfo = $this->queryFactory->newInsert();

        $cols = [
            'user_id' => $id,
            'job_title' => $data['job_title'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'image' => $data['image'] ?? 'img/demo/avatars/avatar-m.png',
            'status_id' => $data['status_id'] ?? '3'
        ];

        // Фильтрация пустых значений
        $cols = array_filter($cols, function($value) {
            return $value !== null;
        });

        $insertUserInfo
            ->into($userInfoTable)
            ->cols($cols);

        $sthInsertUserInfo = $this->pdo->prepare($insertUserInfo->getStatement());

        // выполнение с привязанными значениями
        $sthInsertUserInfo->execute($insertUserInfo->getBindValues());
        header('location:/users');
        exit;
    }

    // если запись создана, то просто обновляем

    if (!empty($data['job_title']) || !empty($data['phone']) || !empty($data['address']) || isset($data['status_id']) || isset($data['image'])) {
        $updateUserInfo = $this->queryFactory->newUpdate();

        $cols = [
            'job_title' => $data['job_title'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'image' => $data['image'] ?? null,
            'status_id' => $data['status_id'] ?? null
        ];

        // Фильтрация пустых значений
        $cols = array_filter($cols, function($value) {
            return $value !== null;
        });

        $updateUserInfo
            ->table($userInfoTable)
            ->cols($cols)
            ->where('user_id = :user_id')
            ->bindValue('user_id', $id);

        $sthUserInfo = $this->pdo->prepare($updateUserInfo->getStatement());

        // выполнение с привязанными значениями
        $sthUserInfo->execute($updateUserInfo->getBindValues());
        //флэш
        if(!empty($data['image']) ){
            $this->flash->message('Ваш аватар был обновлен');
            header('location:/users');
            exit;
        }
        if(!empty($data['job_title']) ||!empty($data['phone']) ||!empty($data['address']) ){
            $this->flash->message('Ваша общая информация была обновлена');
            header('location:/users');
            exit;
        }

        if(!empty($data['status_id'])){
            $this->flash->message('Ваш Статус был обновлен');
            header('location:/users');
            exit;
        }
        header('location:/users');
        exit;
    }
}
        return true;
    }

    public function is_valid_password($password): bool|string
    {

        // Check if password is empty
        if (empty($password) ) {
            // Возвращаем false, если пароль пустой
            return false;
        }

        if (strlen($password) < 3) {
            $this->flash->error('У вас меньше 3х символов');
            return false;
        }

        // Возвращаем хэш пароля
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public function is_valid_email($data): bool
    {
        if (array_key_exists('email', $data)) {
            if (!empty($data['email']) && filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {


                $email_parts = explode('@', $data['email']);
                // Проверка количества символов в имени пользователя
                if (strlen($email_parts[0]) < 3) {
                    echo 'Имя пользователя в email должно содержать не менее трех символов';
                    return false;
                }

                $email_db = $this->getUserById($data['id']);

                if ($email_db !== false && $email_db['email'] === $data['email']) {

                    echo 'в бд уже существует такой email';

                    $this->flash->error('в бд уже существует такой email');
                    header('location:/users');
                    exit();
                }

                $select = $this->queryFactory->newSelect();
                $select->cols(['COUNT(*) AS count'])
                    ->from('users')
                    ->where('email = :email');
                $sth = $this->pdo->prepare($select->getStatement());
                // bind the values and execute
                $sth->bindValue(':email', $data['email'], PDO::PARAM_STR); // Обратите внимание на двоеточие
                $sth->execute();
                $result = $sth->fetch(PDO::FETCH_ASSOC);

                if ($result['count'] > 0) {
                    echo "такой мейл уже занят";
                    return false;
                } else {
                    echo "Можете обновлять email";
                    return true;
                }

            } else {
                echo 'не валидный email';
                return false;
            }
        }
    }

    public function getUserById($id){

            $select = $this->queryFactory->newSelect();

            $select
                ->from('users ')
                ->cols([
                    'users.id',
                    'users.email',
                    'users.username',

                    'user_information.job_title',
                    'user_information.phone',
                    'user_information.address',
                    'user_information.image',
                    'user_information.status_id',

                    'user_social.vk',
                    'user_social.telegram',
                    'user_social.instagram'

                    ])
                ->join(
                    'LEFT',
                    'user_information',
                    'users.id = user_information.user_id'
                )
                ->join(
                    'LEFT',
                    'user_social',
                    'users.id = user_social.user_id'
                )
                ->join(
                    'LEFT',
                    'status',
                    'status.id = user_information.status_id'
                )

                ->where('users.id = :id');

                $sth = $this->pdo->prepare($select->getStatement());

                $sth->bindValue(':id', $id, PDO::PARAM_INT); // Параметр :id типа INT

                $sth->execute();

                return $sth->fetch(PDO::FETCH_ASSOC);

    }
}