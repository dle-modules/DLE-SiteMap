<?php

/*
=============================================================================
 Файл: sitemap.php (frontend) версия 2.2
-----------------------------------------------------------------------------
 Автор: Фомин Александр Алексеевич, mail@mithrandir.ru
-----------------------------------------------------------------------------
 Сайт поддержки: http://alaev.info/blog/post/1974
-----------------------------------------------------------------------------
 Параметры модуля, передаваемые через из tpl при вставке:
    * need_cats     - список id категорий через запятую для вывода в карте
    * exc_cats      - список id категорий через запятую, исключаемых из карты
    * need_news     - список id статей через запятую для вывода в карте
    * exc_news      - список id статей через запятую, исключаемых из карты
    * need_static   - список id статических страниц через запятую для вывода в карте
    * exc_static    - список id статических страниц через запятую, исключаемых из карты
    * cats_as_links - показывать названия категорий как ссылки (1 или 0)
    * show_static   - отображать в карте статические страницы (1 или 0)
    * cats_sort     - поле сортировки списка категорий
    * cats_msort    - направление сортировки списка категорий
    * news_sort     - поле сортировки списка статей
    * news_msort    - направление сортировки списка статей
    * static_sort   - поле сортировки списка статических страниц
    * static_msort  - направление сортировки списка статических страниц
    * cats_limit    - максимальное количество выводимых подкатегорий
    * news_limit    - максимальное количество выводимых статей из категории
    * static_limit  - максимальное количество выводимых статических страниц
-----------------------------------------------------------------------------
 Описание CSS классов карты сайта:
    .sitemap_categories         - элемент <ul> списка категорий
    .sitemap_categories li      - элемент <li> списка категорий
    .sitemap_items              - элемент <ul> списка статей
    .sitemap_items li           - элемент <li> списка статей
    .sitemap_static_pages       - элемент <ul> списка статических страниц
    .sitemap_static_pages li    - элемент <li> списка статических страниц

    .root - класс для вышеперечисленных элементов, находящихся в корне списка
    
    .sitemap_categories a       - элемент <a> c названием категории
    .sitemap_categories span    - элемент <span> c названием категории
    .sitemap_items a            - элемент <a> c названием статьи
    .sitemap_static_pages a     - элемент <a> c названием статической страницы
-----------------------------------------------------------------------------
 Назначение: вывод карты сайта
=============================================================================
*/
    // Антихакер
    if( ! defined( 'DATALIFEENGINE' ) ) {
            die( "Hacking attempt!" );
    }

    /*
     * Класс для создания карты сайта
     */
    class Sitemap
    {
        /*
         * Конструктор класса Sitemap - задаёт значения dle_api и sitemap_config и _time
         * @param $dle_api - объект класса DLE_API
         * @param $sitemap_config - массив с конфигурацией модуля
         * @param $_TIME - время в UNIX формате с учетом настроек смещения в настройках скрипта
         */
        public function __construct($dle_api, $sitemap_config, $_TIME)
        {
            // Определяем объект DLE API
            $this->dle_api = $dle_api;

            // Текущее время в UNIX формате с учетом настроек смещения в настройках скрипта
            $this->_time = $_TIME;

            // Конфигурация модуля
            $this->sitemap_config = $sitemap_config;
        }


        /*
         * Метод генерирует дерево статей, категорий и статических страниц на сайте
         * @return string
         */
        public function tree()
        {
            // В переменную $site_tree будем класть результат
            $site_tree = '';

            // Отображаем все статьи из корня
            $site_tree .= $this->show_cat_items(0);

            // Рекурсивно выводим все категории, подкатегории и вложенные в них статьи
            $site_tree .= $this->show_cats(0);

            // Если в настройках модуля запрашивается вывод статических страниц, выводим их
            if($this->sitemap_config['show_static'])
            {
                $site_tree .= $this->show_static();
            }

            // Копирайт
            $site_tree .= '<div style="display: block !important; text-align: right !important;"><a style="display: inline !important;" href="http://alaev.info/blog/post/1974?from=SiteMap">DLE SiteMap by alaev.info</a></div>';

            // Возвращаем результат
            return $site_tree;
        }


        /*
         * Метод для отображения всех подкатегорий в данной категории
         * @param $parent_id - идентификатор категории-родителя
         * @return string
         */
        public function show_cats($parent_id)
        {
            // В переменную $cats_html будем класть складывать результаты для вывода
            $cats_html = '';

            // Получаем все подкатегории данной категории
            $cats = $this->take_cats($parent_id);

            if($cats)
            {
                // Открываем блок
                $cats_html = '<ul class="sitemap_categories'.(($parent_id == 0)?' root':'').'">';

                // Перебираем все категории из массива категорий
                foreach($cats as $cat)
                {
                    // Открываем блок категории
                    $cats_html .= '<li'.(($parent_id == 0)?' class="root"':'').'>';

                    // Если установлен параметр cats_as_links, показываем ссылку на категорию, если нет - просто текстовое наименование
                    if($this->sitemap_config['cats_as_links'])
                    {
                        $cats_html .= '<a href="'.$this->create_cat_url($cat).'">'.stripslashes($cat['name']).'</a>';
                    }
                    else
                    {
                        $cats_html .= '<span>'.stripslashes($cat['name']).'</span>';
                    }
                    

                    // Выводим статьи из категории
                    $cats_html .= $this->show_cat_items($cat);

                    // Выводим подкатегории
                    $cats_html .= $this->show_cats($cat['id']);

                    // Закрываем блок
                    $cats_html .= '</li>';
                }

                // Закрываем блок
                $cats_html .= '</ul>';
            }
            
            // Возвращаем результат
            return $cats_html;
        }


        /*
         * Метод для отображения всех статей в данной категории
         * @param $cat - массив в информацией о категории - необходим для выяснения параметров сортировки статей
         * @return string
         */
        public function show_cat_items($cat)
        {
            // id категории
            $cat_id = intval($cat['id']);
            
            // В переменную $items_html будем класть складывать результаты для вывода
            $items_html ='';
            
            // Получаем все статьи из данной категории
            $items = $this->take_cat_items($cat);

            if($items)
            {
                // Открываем блок
                $items_html = '<ul class="sitemap_items'.(($cat_id == 0)?' root':'').'">';

                // Перебираем все статьи
                foreach($items as $item)
                {
                    $items_html .= '<li'.(($cat_id == 0)?' class="root"':'').'>';
                    $items_html .= '<a href="'.$this->getPostUrl($item).'">'.stripslashes($item['title']).'</a>';
                    $items_html .= '</li>';
                }

                // Закрываем блок
                $items_html .= '</ul>';
            }

            // Возвращаем результат
            return $items_html;
        }


        /*
         * Метод для отображения всех статических страниц в карте
         * @return string
         */
        public function show_static()
        {
            // В переменную $static_html будем класть складывать результаты для вывода
            $static_html ='';

            // Получаем все статические страницы из базы данных
            $static_pages = $this->take_static_pages();

            if($static_pages)
            {
                // Открываем блок
                $static_html = '<ul class="sitemap_static_pages root">';

                // Перебираем все статические страницы
                foreach($static_pages as $static)
                {
                    $static_html .= '<li class="root">';
                    $static_html .= '<a href="'.$this->create_static_url($static).'">'.stripslashes($static['descr']).'</a>';
                    $static_html .= '</li>';
                }

                // Закрываем блок
                $static_html .= '</ul>';
            }

            // Возвращаем результат
            return $static_html;
        }


        /*
         * @param $parent_id - идентификатор категории-родителя
         * @return array всех подкатегорий из указанной категории
         */
        public function take_cats($parent_id)
        {
            // Список нужных полей из таблицы с категориями
            $fields = 'id, name, alt_name, news_sort, news_msort';

            // Условия поиска будем класть в массив $wheres
            $wheres = array();

            // Условие на необходимые для вывода позиции
            if(!empty($this->sitemap_config['need_cats']))
            {
                $wheres[] = 'id IN ('.$this->sitemap_config['need_cats'].')';
            }

            // Условие на исключаемые из вывода позиции
            if(!empty($this->sitemap_config['exc_cats']))
            {
                $wheres[] = 'id NOT IN ('.$this->sitemap_config['exc_cats'].')';
            }
            
            // Условие на id категории-родителя
            $wheres[] = 'parentid = '.$parent_id;

            // Объединяем условия поиска в строку $condition
            $condition = implode(' AND ', $wheres);

            // Поле и порядок сортировки - из массива sitemap_config
            $sort = $this->sitemap_config['cats_sort'];
            $sort_order = $this->sitemap_config['cats_msort'];

            // Лимит
            $limit = !empty($this->sitemap_config['cats_limit'])?$this->sitemap_config['cats_limit']:'';

            // Возвращаем массив с результатом
            return $this->dle_api->load_table (PREFIX."_category", $fields, $condition, true, 0, $limit, $sort, $sort_order);
        }


        /*
         * часть кода позаимствована из метода take_news DLE API
         * @param $cat - массив в информацией о категории - необходим для выяснения параметров сортировки статей
         * @return array всех статей из категории $cat
         */
        public function take_cat_items($cat)
        {
            // id категории
            $cat_id = intval($cat['id']);
            
            // Список нужных полей из таблицы со статьями
            $fields = 'id, category, title, alt_name, date';
            $fields.= $this->dle_api->dle_config['version_id'] < 9.6?', flag':''; // для старых dle выбираем поле flag

            // Условия поиска будем класть в массив $wheres
            $wheres = array();

            // Условие на необходимые для вывода позиции
            if(!empty($this->sitemap_config['need_news']))
            {
                $wheres[] = 'id IN ('.$this->sitemap_config['need_news'].')';
            }

            // Условие на исключаемые из вывода позиции
            if(!empty($this->sitemap_config['exc_news']))
            {
                $wheres[] = 'id NOT IN ('.$this->sitemap_config['exc_news'].')';
            }

            // Условие поиска - id категории (в зависимости от настроек мультикатегорий DLE)
            if ($this->dle_api->dle_config['allow_multi_category'] == 1)
            {
                $wheres[] = 'category regexp "[[:<:]]('.str_replace(',', '|', $cat_id).')[[:>:]]"';
            }
            else
            {
                $wheres[] = 'category = '.$cat_id;
            }

            // Условие для отображения только статей, прошедших модерацию
            $wheres[] = 'approve = 1';

            // Условие для отображения только тех статей, дата публикации которых уже наступила
            $wheres[] = 'date < "'.date("Y-m-d H:i:s", $this->_time).'"';

            // Объединяем условия поиска в строку $condition
            $condition = implode(' AND ', $wheres);

            // Поле сортировки - из массива sitemap_config, настроек текущей категории или глобальных настроек DLE
            if(!empty($this->sitemap_config['news_sort']))
            {
                $sort = $this->sitemap_config['news_sort'];
            }
            elseif(!empty($cat['news_sort']))
            {
                $sort = $cat['news_sort'];
            }
            else
            {
                $sort = $this->dle_api->dle_config['news_sort'];
            }

            // Порядок сортировки - из массива sitemap_config, настроек текущей категории или глобальных настроек DLE
            if(!empty($this->sitemap_config['news_msort']))
            {
                $sort_order = $this->sitemap_config['news_msort'];
            }
            elseif(!empty($cat['news_msort']))
            {
                $sort_order = $cat['news_msort'];
            }
            else
            {
                $sort_order = $this->dle_api->dle_config['news_msort'];
            }

            // Лимит
            $limit = !empty($this->sitemap_config['news_limit'])?$this->sitemap_config['news_limit']:'';

            // Возвращаем массив с результатом
            return $this->dle_api->load_table (PREFIX."_post", $fields, $condition, true, 0, $limit, $sort, $sort_order);
        }


        /*
         * @return array массив всех статических страниц на сайте
         */
        public function take_static_pages()
        {
            // Список нужных полей из таблицы статических страниц
            $fields = 'id, name, descr';

            // Условия поиска будем класть в массив $wheres
            $wheres = array();

            // Условие на необходимые для вывода позиции
            if(!empty($this->sitemap_config['need_static']))
            {
                $wheres[] = 'id IN ('.$this->sitemap_config['need_static'].')';
            }

            // Условие на исключаемые из вывода позиции
            if(!empty($this->sitemap_config['exc_static']))
            {
                $wheres[] = 'id NOT IN ('.$this->sitemap_config['exc_static'].')';
            }

            // Условие, чтобы время публикации было меньше текущего времени
            $wheres[] = 'date < '.$this->_time;

            // Объединяем условия поиска в строку $condition
            $condition = implode(' AND ', $wheres);

            // Поле и порядок сортировки - из массива sitemap_config
            $sort = $this->sitemap_config['static_sort'];
            $sort_order = $this->sitemap_config['static_msort'];

            // Лимит
            $limit = !empty($this->sitemap_config['static_limit'])?$this->sitemap_config['static_limit']:'';

            // Возвращаем массив с результатом
            return $this->dle_api->load_table (PREFIX."_static", $fields, $condition, true, 0, $limit, $sort, $sort_order);
        }


        /*
         * @param $cat - массив с информацией о категории
         * @return string URL для категории
         */
        public function create_cat_url($cat)
        {
			if($this->dle_api->dle_config['allow_alt_url'] && $this->dle_api->dle_config['allow_alt_url'] != "no")
            {
                $url = $this->dle_api->dle_config['http_home_url'].get_url($cat['id']).'/';
            }
            else
            {
                $url = $this->dle_api->dle_config['http_home_url'].'index.php?do=cat&category='.$cat['alt_name'].'/';
            }

            return $url;
        }


        /*
         * @param $post - массив с информацией о статье
         * @return string URL для категории
         */
        public function getPostUrl($post)
        {
			if($this->dle_api->dle_config['allow_alt_url'] && $this->dle_api->dle_config['allow_alt_url'] != "no")
            {
                if(
                    ($this->dle_api->dle_config['version_id'] < 9.6 && $post['flag'] && $this->dle_api->dle_config['seo_type'])
                        ||
                    ($this->dle_api->dle_config['version_id'] >= 9.6 && ($this->dle_api->dle_config['seo_type'] == 1 || $this->dle_api->dle_config['seo_type'] == 2))
                )
                {
                    if(intval($post['category']) && $this->dle_api->dle_config['seo_type'] == 2)
                    {
                        $url = $this->dle_api->dle_config['http_home_url'].get_url(intval($post['category'])).'/'.$post['id'].'-'.$post['alt_name'].'.html';
                    }
                    else
                    {
                        $url = $this->dle_api->dle_config['http_home_url'].$post['id'].'-'.$post['alt_name'].'.html';
                    }
                }
                else
                {
                    $url = $this->dle_api->dle_config['http_home_url'].date("Y/m/d/", strtotime($post['date'])).$post['alt_name'].'.html';
                }
            }
            else
            {
                $url = $this->dle_api->dle_config['http_home_url'].'index.php?newsid='.$post['id'];
            }

            return $url;
        }


        /*
         * @param $static - массив с информацией о статической странице
         * @return string  URL для статической страницы
         */
        public function create_static_url($static)
        {
			if($this->dle_api->dle_config['allow_alt_url'] && $this->dle_api->dle_config['allow_alt_url'] != "no")
            {
                $url = $this->dle_api->dle_config['http_home_url'].$static['name'].'.html';
            }
            else
            {
                $url = $this->dle_api->dle_config['http_home_url']."index.php?do=static&page=".$static['name'];
            }

            return $url;
        }
    }
    /*---End Of Sitemap Class---*/


    // Подключаем DLE API
    include ('engine/api/api.class.php');
    
    // $site_tree - дерево карты сайта
    $site_tree = false;

    // Если кеширование включено, пробуем получить сорержимое дерева из кеша
    if($dle_api->dle_config['allow_cache'] && $this->dle_api->dle_config['allow_cache'] != "no")
    {
        $site_tree = $dle_api->load_from_cache('site_tree');
    }

    // Если в кеше ничего нет, генерируем дерево
    if($site_tree === false)
    {
        // Конфигурация модуля
        $sitemap_config = array();
        $sitemap_config['need_cats']        = !empty($need_cats)?$need_cats:false;
        $sitemap_config['exc_cats']         = !empty($exc_cats)?$exc_cats:false;
        $sitemap_config['need_news']        = !empty($need_news)?$need_news:false;
        $sitemap_config['exc_news']         = !empty($exc_news)?$exc_news:false;
        $sitemap_config['need_static']      = !empty($need_static)?$need_static:false;
        $sitemap_config['exc_static']       = !empty($exc_static)?$exc_static:false;
        $sitemap_config['cats_as_links']    = !empty($cats_as_links)?true:false;
        $sitemap_config['show_static']      = !empty($show_static)?true:false;
        $sitemap_config['cats_sort']        = !empty($cats_sort)?$cats_sort:'posi';
        $sitemap_config['cats_msort']       = !empty($cats_msort)?$cats_msort:'ASC';
        $sitemap_config['news_sort']        = !empty($news_sort)?$news_sort:false;
        $sitemap_config['news_msort']       = !empty($news_msort)?$news_msort:false;
        $sitemap_config['static_sort']      = !empty($static_sort)?$static_sort:'date';
        $sitemap_config['static_msort']     = !empty($static_msort)?$static_msort:'ASC';
        $sitemap_config['cats_limit']       = !empty($cats_limit)?$cats_limit:false;
        $sitemap_config['news_limit']       = !empty($news_limit)?$news_limit:false;
        $sitemap_config['static_limit']     = !empty($static_limit)?$static_limit:false;

        // Создаём объект $sitemap, передавая конструктору объект $dle_api, массив $sitemap_config с конфигурацией модуля и $_TIME
        $sitemap = new Sitemap($dle_api, $sitemap_config, $_TIME);

        // Получаем дерево из объекта sitemap
        $site_tree = $sitemap->tree();

        // Если кэширование включено, сохраняем дерево в кеш
        if($dle_api->dle_config['allow_cache'] && $this->dle_api->dle_config['allow_cache'] != "no")
        {
            $dle_api->save_to_cache('site_tree', $site_tree);
        }
    }

	$canonical = false;

    // Подключаем файл шаблона sitemap.tpl, заполняем его
    $tpl = new dle_template();
    $tpl->dir = TEMPLATE_DIR;
    $tpl->load_template('sitemap.tpl');
    $tpl->set('{site_tree}', $site_tree);
    $tpl->compile('sitemap');

    // Выводим результат
    echo $tpl->result['sitemap'];
    
?>