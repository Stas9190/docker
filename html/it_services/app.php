<?php

/**
 * Класс, реализующий функционал приложения на стороне сервера
 */

include_once("ssa/classes/render/render.php");
require_once("ssa/classes/db/db_connection.php");
include_once("models.php");
include_once("ssa/classes/moldmaker.php");
include_once("ssa/classes/ssa_files.php");

use Render\Render;
use DBConnection\Command;
use ssa_files\ssa_files;

class App
{
    private $user = null;
    private $article = null;
    private $service = null;
    private $service_type = null;
    private $contacts = null;
    private $feedback = null;
    private $request = null;

    function __construct()
    {
        $this->user = new models\User();
        $this->article = new models\Article();
        $this->service = new models\Service();
        $this->service_type = new models\ServiceType();
        $this->contacts = new models\Contacts();
        $this->feedback = new models\Feedback();
        $this->request = new models\Request();
    }

    //Стартовая страница
    function loadStart()
    {
        echo 'it works~';
        $render = new Render("templates/start_page.php", $this->service->take_few_services(4));
        $render->renderPage();
    }

    function login()
    {
        $render = new Render("templates/login.php");
        $render->renderPage();
    }

    function register()
    {
        $render = new Render("templates/register.php");
        $render->renderPage();
    }

    /** Метод авторизации */
    function authorization($input)
    {
        /**
         *  Получаем зарегистрированного пользователя по логину и паролю 
         * */
        $res = $this->user->get_auth_user($input['login-username'], $input['login-password']);
        /**
         *  Проверяем нашелся ли такой пользователь в базе 
         */
        if (count($res['data']) == 0) {
            exit('Нет такого пользователя');
        }

        /** 
         * Записываем в массив сессии данные пользователя
         */
        $this->create_user_session($res['data']);
        /**
         * Переадресация на главную страницу
         */
        header('Location: http://localhost/it_services/index.php');
    }

    /**
     *  Метод, который записывает в массив сессии данные пользователя 
     */
    function create_user_session()
    {
        /**
         *  id сессии 
         */
        $_SESSION['sid'] = session_id();
        /**
         * Данные пользователя
         */
        $_SESSION['sid'] = func_get_args();
    }

    function logout()
    {
        unset($_SESSION['sid']);
        header('Location: http://localhost/it_services/index.php');
    }

    function contacts()
    {
        $render = new Render("templates/contact-us.php");
        $render->renderPage();
    }

    function about()
    {
        $context['articles'] = $this->article->get_articles();
        $context['article'] = $this->article->getAboutArticle();
        $render = new Render("templates/about-us.php", $context);
        $render->renderPage();
    }

    function updateAboutArticle($input)
    {
        $this->article->updateAboutArticle($input['article_id']);
        echo 0;
        return;
    }

    function articles()
    {
        $render = new Render("templates/blog-posts.php", $this->article->getPublishedArticles());
        $render->renderPage();
    }

    /** 
     * Метод регистрации
     */
    function registration($input)
    {
        $data = array();

        /** 
         * Преобразовываем пришедшие данные в ассоциативный массив 
         */
        foreach (explode('&', $input['data']) as $val) {
            preg_match_all("#([^,\s]+)=([^\*]+)#s", $val, $out);
            unset($out[0]);

            $out = array_combine($out[1], $out[2]);
            $data = array_merge($data, $out);
        }

        /**
         * Проверяем есть ли в массиве элементы
         */
        if (count($data) == 0) {
            echo 1;
            return;
        }

        /**
         * Сверям пароли
         */
        if ($data['register-password'] != $data['register-password2']) {
            echo 2;
            return;
        }

        /**
         * Проверяем существует ли пользователь с таким же именем
         */
        if (!$this->user->checkUser($data['register-username'])) {
            echo 3;
            return;
        }

        /** 
         * Создаем такого пользователя в бд
         */
        $this->user->mail = $data['register-username'];
        $this->user->password = $data['register-password'];
        $this->user->create_user();
        echo 0;
        return;
    }

    /**
     * Метод подгрузки личного кабинета
     */
    function lk()
    {
        $render = null;

        /**
         * В зависимости от привелегий подгружаем разный личный кабинет
         */
        if (isset($_SESSION['sid'][0][0]['level']) == 0) {
            $render = new Render("templates/lk.php");
        } else {
            $render = new Render("templates/admin_lk.php");
        }

        /**
         * Подгружаем шаблон
         */
        $render->renderPage();
    }

    /** 
     * Администрирование 
     */

     /**
      * Методы, которые подгружают необходимый шаблон в зависимости от выбранного пункта списка
      * Если уровень привелегий поззволяет, то подгружаем шаблон
      */

      /** Редактировать статьи */
    function edit_articles()
    {
        if ($_SESSION['sid'][0][0]['level'] > 0) {
            $render = new Render('templates/articles_list.php', $this->article->get_articles());
            $render->renderPage();
        }
    }

    /** Редактировать услуги */
    function edit_services()
    {
        if ($_SESSION['sid'][0][0]['level'] > 0) {
            $render = new Render('templates/services_list.php', $this->service->get_services());
            $render->renderPage();
        }
    }

    /** Редактировать пользователей */
    function edit_users()
    {
        if ($_SESSION['sid'][0][0]['level'] > 0) {
            $render = new Render('templates/users_list.php', $this->user->get_users());
            $render->renderPage();
        }
    }

    /** Редактировать типы статей */
    function types()
    {
        if ($_SESSION['sid'][0][0]['level'] > 0) {
            $render = new Render('templates/types_list.php', $this->service_type->get_types());
            $render->renderPage();
        }
    }

    function add_article()
    {
        $render = new Render("templates/add_article.php");
        $render->renderPage();
    }

    function add_service()
    {
        $render = new Render("templates/add_service.php", $this->service_type->get_types());
        $render->renderPage();
    }

    function add_type()
    {
        $render = new Render("templates/add_type.php");
        $render->renderPage();
    }

    /**
     * Метод создания статьи
     */
    function createArticle($input)
    {
        $data = array();

        /**
         * Заполняе масив данными 
         */
        foreach (explode('&', $input['data']) as $val) {
            preg_match_all("#([^,\s]+)=([^\*]+)#s", $val, $out);
            unset($out[0]);

            $out = array_combine($out[1], $out[2]);
            $data = array_merge($data, $out);
        }

        /**
         * Создаем статью
         */
        $this->article->name = urldecode($data['name']);
        $this->article->article_text = urldecode($data['article_text']);
        $this->article->status = $data['status'];
        $this->article->create_article();
        echo 0;
        return;
    }

    function createType($input)
    {
        $data = array();

        foreach (explode('&', $input['data']) as $val) {
            preg_match_all("#([^,\s]+)=([^\*]+)#s", $val, $out);
            unset($out[0]);

            $out = array_combine($out[1], $out[2]);
            $data = array_merge($data, $out);
        }

        $this->service_type->name = urldecode($data['name']);
        $this->service_type->create_type();
        echo 0;
        return;
    }

    /**
     * Метод создания услуги
     */
    function createService($input)
    {
        $data = array();

        /**
         * Приводим данные к нормальному виду
         */
        foreach (explode('&', $input['data']) as $val) {
            preg_match_all("#([^,\s]+)=([^\*]+)#s", $val, $out);
            unset($out[0]);

            $out = array_combine($out[1], $out[2]);
            $data = array_merge($data, $out);
        }

        /**
         * Создаем услугу
         */
        $this->service->id_type = $data['id_type'];
        $this->service->name = urldecode($data['name']);
        $this->service->description = urldecode($data['description']);
        $this->service->price = $data['price'];
        $this->service->note = isset($data['note']) ? urldecode($data['note']) : '';
        $this->service->create_service();
        echo 0;
        return;
    }

    /**
     * Метод при нажатии на иконку "редактировать статью" 
     */
    function edit_article($input, $parms)
    {
        /**
         * Проверяем уровень доступа пользователя
         */
        if ($_SESSION['sid'][0][0]['level'] > 0) {
            /**
             * Подключаем файл с шаблоном и передаем в него набор данных со статьями
             */
            $render = new Render("templates/edit_article.php", $this->article->get_article($parms[0]));
            $render->renderPage();
        }
    }

    /** 
     * Метод при нажатии на иконку "редактировать услугу" 
     */
    function edit_service($input, $parms)
    {
        /**
         * Проверяем уровень доступа пользователя
         */
        if ($_SESSION['sid'][0][0]['level'] > 0) {
            /**
             * Подключаем файл с шаблоном и передаем в него наборы данных с услугами и с типами услуг
             */
            $render = new Render("templates/edit_service.php", ['main' => $this->service->get_service($parms[0]), 'types' => $this->service_type->get_types()]);
            $render->renderPage();
        }
    }

    /**
     * Метод при нажатии на кнопку редактировать пользователя
     */
    function edit_user($input, $parms)
    {
        /**
         * Проверяем уровень длступа
         */
        if ($_SESSION['sid'][0][0]['level'] > 0) {
            /**
             * Подгружаем нужный шаблон, передаем в него id пользователя
             */
            $render = new Render("templates/edit_user.php", $this->user->get_user($parms[0]));
            $render->renderPage();
        }
    }

    function profile_edit()
    {
        $render = new Render("templates/profile_edit.php", $this->user->get_user($_SESSION['sid'][0][0]['id']));
        $render->renderPage();
    }

    function edit_contacts($input, $parms)
    {
        if ($_SESSION['sid'][0][0]['level'] > 0) {
            $render = new Render("templates/edit_contacts.php", $this->contacts->get_contacts());
            $render->renderPage();
        }
    }

    function edit_type($input, $parms)
    {
        if ($_SESSION['sid'][0][0]['level'] > 0) {
            $render = new Render("templates/edit_type.php", $this->service_type->get_type($parms[0]));
            $render->renderPage();
        }
    }

    /**
     * Метод обновления статьи
     */
    function updateArticle($input)
    {
        /** 
         * Заполняем массив данными 
         */
        $data = $this->parseInput($input);

        /**
         *  Обновляем статью
         */
        $this->article->name = urldecode($data['name']);
        $this->article->article_text = urldecode($data['article_text']);
        $this->article->status = $data['status'];
        $this->article->update_article($data['id']);
        echo 0;
        return;
    }

    /**
     * Метод обновления услуги
     */
    function updateService($input)
    {
        /** 
         * Получаем данные из формы и преобразуем их в нопмальный вид 
         */
        $data = $this->parseInput($input);

        /** 
         * Обновляем услугу 
         */
        $this->service->id_type = $data['id_type'];
        $this->service->name = urldecode($data['name']);
        $this->service->description = urldecode($data['description']);
        $this->service->price = $data['price'];
        $this->service->note = isset($data['note']) ? urldecode($data['note']) : '';
        $this->service->update_service($data['id']);
        echo 0;
        return;
    }

    function service($input, $parms)
    {
        $context['main'] = $this->service->get_service($parms[0]);
        $context['recent_articles'] = $this->article->getRecentArticles(3);
        $render = new Render("templates/service-left-sidebar.php", $context);
        $render->renderPage();
    }

    function updateType($input)
    {
        $data = $this->parseInput($input);

        $this->service_type->name = urldecode($data['name']);
        $this->service_type->update_type($data['id']);
        echo 0;
        return;
    }

    /**
     * Метод обновления данных пользователя
     */
    function updateUser($input)
    {
        /** Получаем данные в виде массива */
        $data = $this->parseInput($input);

        /** 
         * Заполняем необходимые поля класса и вызываем метод обновления
         */
        $this->user->login = isset($data['login']) ? $data['login'] : '';
        $this->user->mail = isset($data['mail']) ? urldecode($data['mail']) : '';
        $this->user->fio = isset($data['fio']) ? urldecode($data['fio']) : '';
        $this->user->tel = isset($data['tel']) ? $data['tel'] : '';
        $this->user->level = isset($data['level']) ? $data['level'] : 0;
        $this->user->status = $data['status'];
        $this->user->update_user($data['id']);
        echo 0;
        return;
    }

    function update_profile($data)
    {
        $this->user->login = isset($data['login']) ? $data['login'] : '';
        $this->user->mail = isset($data['mail']) ? urldecode($data['mail']) : '';
        $this->user->fio = isset($data['fio']) ? urldecode($data['fio']) : '';
        $this->user->tel = isset($data['tel']) ? $data['tel'] : '';
        $this->user->update_profile($data['id']);
        header('Location: http://localhost/it_services/index.php/lk');
    }

    /**
     * Метод обновления контактной инф-ции
     */
    function updateContacts($input)
    {
        /**
         * Получаем данные, заполняем ими массив
         */
        $data = $this->parseInput($input);

        /**
         * Присваиваем полям класса данные полученные с формы
         */
        $this->contacts->tel = isset($data['tel']) ? $data['tel'] : '';
        $this->contacts->mail = isset($data['mail']) ? urldecode($data['mail']) : '';
        $this->contacts->about = isset($data['about']) ? urldecode($data['about']) : '';
        $this->contacts->address = isset($data['address']) ? urldecode($data['address']) : '';;
        $this->contacts->skype = isset($data['skype']) ? $data['skype'] : 0;

        /** 
         * В зависимотси от того, сохраняем мы или обновляем инф-цию, вызываем разные методы класса contacts
         */
        if (isset($data['id']))
            $this->contacts->update_contacts($data['id']);
        else
            $this->contacts->set_contacts();
        echo 0;
        return;
    }

    private function parseInput($input_data)
    {
        $data = array();

        foreach (explode('&', $input_data['data']) as $val) {
            preg_match_all("#([^,\s]+)=([^\*]+)#s", $val, $out);
            unset($out[0]);

            $out = array_combine($out[1], $out[2]);
            $data = array_merge($data, $out);
        }

        return $data;
    }

    /**
     * Метод при нажатии кнопки удалить статтью
     */
    function delete_article($input, $parms)
    {
        /**
         * Отправляем необходимый набор параметров(класс, id статьи, метод, метод для редиректа).
         */
        if ($_SESSION['sid'][0][0]['level'] > 0) {
            $context = ['article', $parms[0], 'delete_article', 'edit_articles'];
            $render = new Render('templates/confirm_delete.php', $context);
            $render->renderPage();
        }
    }

    /**
     * Метод при нажатии на кнопку удалить
     */
    function delete_service($input, $parms)
    {
        /**
         * Проверяем уровень доступа
         */
        if ($_SESSION['sid'][0][0]['level'] > 0) {
            /**
             * Отправляем необходимый набор параметров(класс, id услуги, метод, метод для редиректа).
             */
            $context = ['service', $parms[0], 'delete_service', 'edit_services'];
            $render = new Render('templates/confirm_delete.php', $context);
            $render->renderPage();
        }
    }

    /**
     * Метод удаления пользователя
     */
    function delete_user($input, $parms)
    {
        /**
         * Проверяем уровень доступа 
         */
        if ($_SESSION['sid'][0][0]['level'] > 0) {
            /**
             * Отправляем необходимый набор параметров(класс, id пользователя, метод, метод для редиректа).
             */
            $context = ['user', $parms[0], 'delete_user', 'edit_users'];
            /**
             * Подгружаем шаблон подтверждения удаления
             */
            $render = new Render('templates/confirm_delete.php', $context);
            $render->renderPage();
        }
    }

    function delete_type($input, $parms)
    {
        if ($_SESSION['sid'][0][0]['level'] > 0) {
            $context = ['service_type', $parms[0], 'delete_type', 'types'];
            $render = new Render('templates/confirm_delete.php', $context);
            $render->renderPage();
        }
    }

    function read($input, $parms)
    {
        $context['main'] = $this->article->get_article($parms[0]);
        $context['recent_articles'] = $this->article->getRecentArticles(3);
        $render = new Render('templates/blog-post-left-sidebar.php', $context);
        $render->renderPage();
    }

    function create_feedback($input)
    {
        $this->feedback->id_user = isset($input['id_user']) ? $input['id_user'] : null;
        $this->feedback->fio = isset($input['fio']) ? $input['fio'] : '';
        $this->feedback->mail = isset($input['mail']) ? $input['mail'] : '';
        $this->feedback->feedback_text = isset($input['feedback_text']) ? $input['feedback_text'] : '';

        $this->feedback->create_record();
        header("Location: http://localhost/it_services/index.php");
    }

    function it_services()
    {
        $context['service_types'] = $this->service_type->get_types();
        $context['service'] = $this->service->get_services();
        $render = new Render("templates/it_services.php", $context);
        $render->renderPage();
    }

    function add_service_to_request($input)
    {
        if (!isset($_SESSION['sid']['services']))
            $_SESSION['sid']['services'] = [];
        if (in_array($input['id'], $_SESSION['sid']['services'])) {
            echo 0;
            return;
        }
        array_push($_SESSION['sid']['services'], $input['id']);
        echo 1;
        return;
    }

    function create_new_request($input)
    {
        $this->request->id_user = $_SESSION['sid'][0][0]['id'];
        $this->request->comment = $input['comment'];
        $this->request->approximate_price = $input['approximate_price'];
        $this->request->services = $_SESSION['sid']['services'];
        $this->request->create_request();
        unset($_SESSION['sid']['services']);
        header("Location: http://localhost/it_services/index.php/requests");
    }

    function requests()
    {
        $context = [];
        if ($_SESSION['sid'][0][0]['level'] == 2) {
            $context['main'] = $this->request->get_all_requests();
        }
        if ($_SESSION['sid'][0][0]['level'] == 1) {
            if (isset($_SESSION['sid']['services'])) {
                $context['services'] = [];
                foreach ($_SESSION['sid']['services'] as $item) {
                    array_push($context['services'], $this->service->get_service($item));
                }
            }
            $context['main'] = $this->request->get_users_requests($_SESSION['sid'][0][0]['id']);
        }
        $render = new Render("templates/requests.php", $context);
        $render->renderPage();
    }

    function change_status_of_request($input)
    {
        $this->request->change_status($input['id']);
        echo 1;
        return;
    }

    function messages()
    {
        $render = new Render('templates/messages.php', $this->feedback->get_messages());
        $render->renderPage();
    }

    function delete_service_from_request($input, $parms)
    {
        if (in_array($parms[0], $_SESSION['sid']['services'])) {
            if (array_search($parms[0], $_SESSION['sid']['services']) !== False) {
                $key = array_search($parms[0], $_SESSION['sid']['services']);
                unset($_SESSION['sid']['services'][$key]);
                if (count($_SESSION['sid']['services']) == 0) {
                    unset($_SESSION['sid']['services']);
                }
                header("Location: http://localhost/it_services/index.php/requests");
            }
        }
    }

    function calc_get_service($input)
    {
        exit(json_encode(($this->service->get_service($input['id']))['data']));
    }

    /** 
     * Метод подтверждения удаленя
     */
    function confirm($input, $parms)
    {
        /**
         * Вызываем метод удаления у класса, который мы передали в этот метод
         */
        $this->{$parms[0]}->{$parms[2]}($parms[1]);
        /**
         * Редирект
         */
        header("Location: http://localhost/it_services/index.php/{$parms[3]}");
    }
}
