<?php
namespace App\Controllers;

use Aura\SqlQuery\QueryFactory;
use Delight\Auth\AttemptCancelledException;
use Delight\Auth\Auth;
use Delight\Auth\AuthError;
use Delight\Auth\Role;
use League\Plates\Engine;
use PDO;
use PDOException;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use App\Controllers\QueryBuilder;
use \Tamtamchik\SimpleFlash\Flash;
use function Tamtamchik\SimpleFlash\flash;
class UserController{


    private Engine $templates;
    /**
     * @var Auth
     */
    private Auth $auth;
    private QueryFactory $queryFactory;
    private PDO $pdo;
    private QueryBuilder $queryBuilder;
    private Flash $flash;

    public function __construct(Engine $engine,Auth $auth,QueryFactory $queryFactory,PDO $pdo,QueryBuilder $queryBuilder){
            $this->templates=$engine;
            $this->auth=$auth;
            $this->queryFactory=$queryFactory;
            $this->pdo=$pdo;
            $this->queryBuilder=$queryBuilder;
            $this->flash=new Flash();
    }



    public function getAllUsers(){

        if ($this->auth->isLoggedIn()) {
          //  $this->flash->message('Вы вошли в систему');
        }
        else {
            header('location:/login');
            exit;
        }
        $usersView = $this->queryBuilder->getAll('users',true,true,true);

        $userRole = $this->auth->hasRole(Role::ADMIN);
        $userId=$this->auth->getUserId();



        echo $this->templates->render('users', ['usersView' => $usersView,'userRole'=>$userRole,'userId'=>$userId]);
    }



    public function login(){
        echo $this->templates->render('page_login', ['POST' => $_POST]);
        if(!empty($_POST)){
            try {

                $this->auth->login($_POST['email'], $_POST['password']);

                $this->flash->message('Добро пожаловать в систему пользователь : '. $this->auth->getEmail());
                header('location:/users');
                exit();
            }
            catch (\Delight\Auth\InvalidEmailException $e) {

                $this->flash->error('Неправильный адрес электронной почты');
                header('location:/login');
                exit;
            }
            catch (\Delight\Auth\InvalidPasswordException $e) {

                $this->flash->error('Неправильный пароль');
                header('location:/login');
                exit;
            }
            catch (\Delight\Auth\EmailNotVerifiedException $e) {

                $this->flash->error('Электронная почта не подтверждена');
                header('location:/login');
                exit;
            }
            catch (\Delight\Auth\TooManyRequestsException $e) {
                $this->flash->error('Слишком много запросов');
                header('location:/login');
                exit;
            } catch (AttemptCancelledException $e) {
            } catch (AuthError $e) {
            }
        }


    }

    public function pageRegister() {
        echo $this->templates->render('page_register');
        exit;
    }
    public function register(){
        if (!empty($_POST)) {
            try {
                $userId = $this->auth->register($_POST['email'], $_POST['password'], 'Новый пользователь', function ($selector, $token) {
                    echo 'Send ' . $selector . ' and ' . $token . ' to the user (e.g. via email)';
                    echo '  For emails, consider using the mail(...) function, Symfony Mailer, Swiftmailer, PHPMailer, etc.';
                    echo '  For SMS, consider using a third-party service and a compatible SDK';
                });

                $this->flash->success('Мы зарегистрировали нового пользователя с идентификатором ID ' . $userId);

                header('location:/login');
                exit();
            } catch (\Delight\Auth\InvalidEmailException $e) {
                $this->flash->error('Неверный адрес электронной почты');
                header('location:/page_register');
                exit();

            } catch (\Delight\Auth\InvalidPasswordException $e) {
                $this->flash->error('Неверный пароль');
                header('location:/page_register');
                exit();
            } catch (\Delight\Auth\UserAlreadyExistsException $e) {
                $this->flash->error(' Этот эл. адрес уже занят другим пользователем.');
                header('location:/page_register');
                exit();
            } catch (\Delight\Auth\TooManyRequestsException $e) {
                $this->flash->error('Слишком много запросов');
                header('location:/page_register');
                exit();
            }
        }


    }

    public function pageCreateUser(){
        echo $this->templates->render('create_user');
exit();
    }
    public function createUser(){

if(!empty($_POST)){
    try {
        $userId = $this->auth->admin()->createUser($_POST['email'], $_POST['password'], 'Новый пользотель');
        /*=======================*/
        $insert = $this->queryFactory->newInsert();
        $image_path=$this->upload_avatar($_FILES['image']);
        $insert->into('user_information')             // insert into this table
        ->cols([                     // insert these columns and bind these values
            'job_title' => $_POST['job_title'],
            'phone' => $_POST['phone'],
            'address' => $_POST['address'],
            'image' =>$image_path,
            'user_id' =>$userId,
            'status_id' =>$_POST['status_id']
        ]);
        $sth = $this->pdo->prepare($insert->getStatement());
        // execute with bound values
        $sth->execute($insert->getBindValues());

        /*=======================*/
        $insert_social = $this->queryFactory->newInsert();
        $insert_social->into('user_social')
            ->cols([                     // insert these columns and bind these values
            'vk' => $_POST['vk'],
            'telegram' => $_POST['telegram'],
            'instagram' => $_POST['instagram'],
            'user_id' =>$userId
        ]);
        $sth_social = $this->pdo->prepare($insert_social->getStatement());
        // execute with bound values
        $sth_social->execute($insert_social->getBindValues());
        //если существует userId то добавляй с м
        /*=======================*/
     //   echo 'Мы зарегистрировали нового пользователя с идентификатором ID ' . $userId;
        $this->flash->message('Мы зарегистрировали нового пользователя:'.$_POST['email']);
        header('location:/users');
        exit();
    }
    catch (\Delight\Auth\InvalidEmailException $e) {
        die('Неверный адрес электронной почты');
    }
    catch (\Delight\Auth\InvalidPasswordException $e) {
        die('Неверный пароль');
    }
    catch (\Delight\Auth\UserAlreadyExistsException $e) {
        die('Пользователь уже существует');
    }

}else{
echo "вы не заполнили форму";
}


    }


    public function editUser($id): void
    {
        $user = $this->queryBuilder->getUserById($id);
       echo $this->templates->render('edit', ['user' => $user]);
    }
    public function updateUser($id): void
    {
        $data = [
            'username' => $_POST['username'],
            'job_title' => $_POST['job_title'],
            'phone' => $_POST['phone'],
            'address' => $_POST['address']
        ];
        $mainTable = 'users'; // имя основной таблицы
        $userInfoTable = 'user_information'; // имя информационной таблицы

            // вызываем функцию update, передавая имена таблиц в качестве параметров
        $this->queryBuilder->update($id, $data, $mainTable, $userInfoTable);

    }
    public function editStatus($id){
        $current_user_status = $this->queryBuilder->getUserById($id);
        $status=$this->queryBuilder->getAll('status');
        $user = $this->queryBuilder->getUserById($id);
        echo $this->templates->render('status', ['status' => $status,'current_user_status'=>$current_user_status,'user'=>$user]);
    }
    public function updateStatus($id){
         $data=[
             'status_id'=>$_POST['status_id']
         ];
       // $mainTable = 'users'; // имя основной таблицы
        $userInfoTable = 'user_information'; // имя информационной таблицы

        // вызываем функцию update, передавая имена таблиц в качестве параметров
        $this->queryBuilder->update($id, $data, null, $userInfoTable);
    }
    public function editMedia($id){
        $user = $this->queryBuilder->getUserById($id);
        var_dump($user);
        echo $this->templates->render('media', ['user' => $user]);
    }
    public function updateMedia($id): void
    {

        if(isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK){
            $user = $this->queryBuilder->getUserById($id);
            $image_path=$this->upload_avatar($_FILES['image']);
            $data=[
                'old_image'=>$user['image'],
                'image'=>$image_path
            ];

            $userInfoTable = 'user_information'; // имя информационной таблицы
            $this->queryBuilder->update($id, $data, null, $userInfoTable);
        }
        //флэш сообщение
        $this->flash->error('Вы не загрузили картинку');
        header('location:/users');
        exit();
    }
    public function page_profile(){
        echo $this->templates->render('page_profile', ['page_profile' => 'Просмотр профиля']);
    }
    public function editSecurity($id){
        $user = $this->queryBuilder->getUserById($id);
        var_dump($user);
        echo $this->templates->render('security', ['user' => $user]);
    }
    public function updateSecurity($id){


        if(!empty($_POST)){
            //если не пуста email и пуста пароль
            if(!empty($_POST['email']) && empty($_POST['password'])){
                $data=[
                    'id'=>$id,
                    'email'=>$_POST['email']
                ];
                    $email=$this->queryBuilder->is_valid_email($data);

                    if(!$email){

                    }else{

                        $mainTable = 'users'; // имя информационной таблицы
                        $security_updated=$this->queryBuilder->update($id, $data, $mainTable, null);
                        header("location:/users");
                       exit();
                    }
                exit();

            }

//если не пуста email и пароль
if(!empty($_POST['email']) && !empty($_POST['password'])){

    $currentUser = $this->queryBuilder->getUserById($id);
    //проверка пароля
    if($_POST['password']==$_POST['password_confirm']){

        $hashed_password=$this->queryBuilder->is_valid_password($_POST['password']);
        if(!$hashed_password){
            header("location:/security/{$id}");
            exit();
        }else{

            $data=[
                'id'=>$id,
                'password'=>$hashed_password
            ];
            if ($_POST['email'] !== $currentUser['email']) {
                $data['email'] = $_POST['email'];
                $email = $this->queryBuilder->is_valid_email($data);

                if (!$email) {
                    header("location:/security/{$id}");
                    exit();
                }
            }
            $mainTable = 'users'; // имя информационной таблицы
            $this->queryBuilder->update($id, $data, $mainTable, null);
            $this->flash->message("вы {$currentUser['email']} изменили пароль");
            header("location:/users");
            exit();
        }

        //  $mainTable = 'users'; // имя информационной таблицы
        // $this->queryBuilder->update($id, $data, $mainTable, $userInfoTable);
    }else{

        $this->flash->error('Вы не подтвердили пароль');
        header("location:/security/{$id}");

    }
    exit();
}
/*конец условии*/

//если меняем только пароль
            if($_POST['password']==$_POST['password_confirm']){

                $hashed_password=$this->queryBuilder->is_valid_password($_POST['password']);
                if(!$hashed_password){
                    header("location:/security/{$id}");
                    exit();
                }else{

                    $data=[
                        'password'=>$hashed_password
                    ];
                    $mainTable = 'users'; // имя информационной таблицы
                    $security_updated=$this->queryBuilder->update($id, $data, $mainTable, null);
                     if($security_updated){
                         echo 'Вы изменили пароль пользователя';
                         $this->flash->message('Вы изменили пароль пользователя');
                         header("location:/users");
                         exit();
                     }else{
                         header("location:/security/{$id}");
                         exit();
                     }
                }

               //  $mainTable = 'users'; // имя информационной таблицы
               // $this->queryBuilder->update($id, $data, $mainTable, $userInfoTable);
            }else{
                echo 'пароль не совпадает';
                header("location:/security/{$id}");

            }
        }else{



            header("location:/security/{$id}");
            exit();
        }

    }
    public function email_verification(){
        try {
            $this->auth->confirmEmail('h8XH852b4bCF1x9A', 'ToLlUkY4RmrQUEHa');

            echo 'Адрес электронной почты подтвержден';
        }
        catch (\Delight\Auth\InvalidSelectorTokenPairException $e) {
            die('Invalid token');
        }
        catch (\Delight\Auth\TokenExpiredException $e) {
            die('Token expired');
        }
        catch (\Delight\Auth\UserAlreadyExistsException $e) {
            die('Email address already exists');
        }
        catch (\Delight\Auth\TooManyRequestsException $e) {
            die('Too many requests');
        }
    }

    /**
     * @throws AuthError
     */
    public function logout(){
        $this->auth->logOut();

        header('location:/login');
        exit();
    }
    public function upload_avatar($image){
            // Директория для загрузки изображений
            //public/img/user_avatar
            $upload_dir = 'views/img/user_avatar/';

            // Если изображение не было загружено, устанавливаем значение по умолчанию
            if (empty($image['tmp_name'])) {
                return 'views/img/demo/avatars/avatar-m.png';
            }

            $image_extension = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));

            // Генерация уникального имени файла
            $unique_name = uniqid() . '.' . $image_extension;


            $upload_path = $upload_dir . $unique_name;


            if (move_uploaded_file($image['tmp_name'], $upload_path)) {
                return 'views/img/user_avatar/' . $unique_name; // Возвращаем путь к изображению с именем файла
            } else {
                // В случае ошибки загрузки возвращаем false
                return false;
            }



        return $upload_path;

    }
    public function deleteUser($id): void
    {

        $user = $this->queryBuilder->getUserById($id);

        // Ensure $user['image'] is not null before using it
        if ($user['image'] !== null) {
            $noFoto = 'views/img/demo/avatars/avatar-m.png';
            if (file_exists($user['image'])) {
                if ($user['image'] !== $noFoto) {
                    unlink($user['image']);
                }
            }
        }

        // Deleting from 'users' table
        $deleteQuery = $this->queryFactory->newDelete();
        $deleteQuery->from('users')
            ->where('id = :id')
            ->bindValue('id', $id);
        $sth_users = $this->pdo->prepare($deleteQuery->getStatement());
        $sth_users->execute($deleteQuery->getBindValues());

        // Deleting from 'user_information' table
        $deleteQuery = $this->queryFactory->newDelete();
        $deleteQuery->from('user_information')
            ->where('user_id = :user_id')
            ->bindValue('user_id', $id);
        $sth_user_information = $this->pdo->prepare($deleteQuery->getStatement());
        $sth_user_information->execute($deleteQuery->getBindValues());

        // Deleting from 'user_social' table
        $deleteQuery = $this->queryFactory->newDelete();
        $deleteQuery->from('user_social')
            ->where('user_id = :user_id')
            ->bindValue('user_id', $id);
        $sth_user_social = $this->pdo->prepare($deleteQuery->getStatement());
        $sth_user_social->execute($deleteQuery->getBindValues());
        $this->flash->message('Пользователь : '.$user['email']." был удален из системы");
        header('location:/users');
        exit();
    }

    /**
     * @throws Exception
     */
    public function send_mailer(): void
    {
        // Подключаем файл класса PHPMailer


// Создаем новый экземпляр PHPMailer
        $mail = new PHPMailer;

// Устанавливаем соединение с SMTP сервером
   //     $mail->isSMTP();

// Указываем адрес SMTP сервера
      //  $mail->Host = 'smtp.mail.ru'; // Укажите адрес вашего SMTP сервера

// Указываем порт SMTP сервера
     //   $mail->Port = 587; // Обычно 587 или 465

// Включаем шифрование TLS
    //    $mail->SMTPSecure = 'tls';

// Устанавливаем режим отладки
   //     $mail->SMTPDebug = 2;

// Указываем учетные данные для авторизации на SMTP сервере
        $mail->SMTPAuth = true;
        $mail->Username = 'almaz_almaz_almaz@mail.ru'; // Укажите вашу почту
        $mail->Password = '123'; // Укажите пароль от почты

// Указываем адрес и имя отправителя
        $mail->setFrom('almaz_almaz_almaz@mail.ru', 'Your Name');

// Указываем адрес и имя получателя
        $mail->addAddress('test@mail.ru', 'Recipient Name'); // Укажите адрес получателя

// Указываем тему письма
        $mail->Subject = 'Тестовое письмо от PHPMailer';

// Указываем текст письма
        $mail->Body = 'Привет, это тестовое письмо от PHPMailer.';

// Отправляем письмо
        if(!$mail->send()) {
            echo 'Ошибка при отправке письма: ' . $mail->ErrorInfo;
        } else {
            echo 'Письмо успешно отправлено';
        }
    }


}
