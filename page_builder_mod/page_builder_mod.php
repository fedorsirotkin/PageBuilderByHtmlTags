<?php

/**
 * Шаблонизатор PageBuilder
 * 
 * Выполняет чтение шаблона страницы и по меткам-комментариям и разбивает его
 * на смысловые блоки, далее, при необходимости, подставляет в блоки данные.
 * Затем собирает шаблон с данными в готовое представление
 * 
 * Автор: Сироткин Ф.А.
 * Дата:  22.12.2018 
 * 
 */
class PageBuilder {

    /**
     * 
     * - - - - - Свойства класса - - - - -
     * 
     */
    private $page;        // Файл шаблона страницы
    private $data;        // Подставляемые данные
    private $blocks;      // Блоки страницы
    private $view;        // Готовые данные

    // Сообщение пользователю, в случае возникновения ошибки

    const ERROR = ERROR_MSG;
    // Метка вложенности
    const PLACE_CHILD = '%place_child%';

    /**
     * 
     * - - - - - Методы класса - - - - -
     * 
     */

    /**
     * Конструктор
     * 
     * @param string $page
     *   Шаблон страницы

     * @param array $data
     *   Подставляемые данные
     * 
     */
    function __construct($page, $data) {
        $this->data = $data;
        $this->page = $this->get_page($page);
        $this->blocks = $this->parse_page();
        $this->view = $this->get_view();
    }

    /**
     * Получение шаблона страницы
     * 
     * @param string $page
     *   Страница в шаблоне
     * 
     * @return string $content
     *   Шаблон страницы или NULL, если такой страницы шаблона нет
     * 
     */
    private function get_page($page) {
        $path = DOCUMENT_ROOT . TEMPLATE_PATH . '/' . $page . '.html';
        $content = '';
        if (file_exists($path)) {
            $content = file_get_contents($path);
        }

        return $content;
    }

    /**
     * Возвращает страницу с данными
     * 
     * @return string $view
     *   Готовые данные в виде строки
     * 
     */
    private function get_view() {
        $view = $this->build_page();
        return $view;
    }

    /**
     * Проверка шаблона на правильность расстановки меток-комментариев
     * 
     * @return bool $result
     *   Результат проверки шаблона
     *  
     */
    private function validate_page() {
        $page = $this->page;
        $result = false;
        // Количество меток begin 
        $pattern_begin = '~<!-- begin-(.*) -->~';
        $mathes_begin = array();
        $count_begin = preg_match_all($pattern_begin, $page, $mathes_begin);
        // Количество меток end
        $pattern_end = '~<!-- end-(.*) -->~';
        $mathes_end = array();
        $count_end = preg_match_all($pattern_end, $page, $mathes_end);

        // Уникальные метки
        $count_labels_uniq_begin = count(array_unique($mathes_begin[1]));
        $count_labels_uniq_end = count(array_unique($mathes_end[1]));

        // Разница между метками        
        $array_diff = array_diff($mathes_begin[1], $mathes_end[1]);

        if ($count_begin === $count_end && $count_labels_uniq_begin === $count_begin && $count_labels_uniq_end === $count_end && count($array_diff) === 0) {
            $result = true;
        } else {
            exit(self::ERROR);
        }
        return $result;
    }

    /**
     * Поиск содержимого в метках
     * 
     * @param string $pattern
     *   Искомый шаблон
     * 
     * @param string $subject
     *   Входная строка 
     * 
     * @return array $blocks
     *   Смысловые блоки
     * 
     */
    private function find_labels($pattern, $subject) {
        $blocks = array();
        $matches = array();
        $count_matches = preg_match_all($pattern, $subject, $matches);
        if ($count_matches > 0) {
            foreach ($matches[2] as $key => $match) {
                $pattern_replace = '~<!-- begin-(.*?) -->(.*)<!-- end-(.*?) -->~s';
                $parent_key = $matches[1][$key];
                $blocks[$parent_key][] = preg_replace($pattern_replace, self::PLACE_CHILD, $match);
                $res_found = $this->find_labels($pattern, $match);
                if (!empty($res_found)) {
                    $blocks[$parent_key][] = $res_found;
                }
            }
        }
        return $blocks;
    }

    /**
     * Парсинг страниц по меткам-комментариям на блоки
     * 
     * @return array $blocks
     *   Все смысловые блоки
     * 
     */
    private function parse_page() {
        $page = $this->page;
        $blocks = array();

        // Шаблон из меток-комментариев правильный?
        if ($this->validate_page()) {
            $pattern_found = '~<!-- begin-(.*?) -->(.*?)<!-- end-(\1) -->~s';
            $subject = $page;
            $blocks = $this->find_labels($pattern_found, $subject);
        } else {
            exit(self::ERROR);
        }

        return $blocks;
    }

    /**
     * Ищет смысловой блок по ключу, если такой ключ найден, то
     * в смысловой блок подставляется новое значение
     * 
     * @param array $blocks
     *   Все смысловые блоки с данными по умолчанию
     * 
     * @param mixed $key
     *   Ключ метки-комментария
     * 
     * @param string $value
     *   Значение, которое надо подставить
     * 
     * @return array $blocks
     *   Все смысловые блоки замененными данными
     * 
     */
    private function replace_data_by_key(&$blocks, $key, $value) {
        // Перебор вложенных блоков
        if (is_array($blocks)) {
            foreach ($blocks as $k => &$block) {
                if (isset($block[$key])) {
                    $block[$key][0] = $value;
                }
                if (is_array($block)) {
                    $block = $this->replace_data_by_key($block, $key, $value);
                }
            }
        }

        return $blocks;
    }

    /**
     * Подставляет данные в смысловые блоки
     * 
     * @param array $blocks
     *   Все смысловые блоки с данными по умолчанию
     * 
     * @return array $blocks
     *   Все смысловые блоки замененными данными
     * 
     */
    private function adding_data($blocks) {
        $data = $this->data;
        foreach ($data as $key => &$value) {
            if (isset($blocks[$key])) {
                $blocks[$key][0] = $value;
            }
            $blocks = $this->replace_data_by_key($blocks, $key, $value);
        }

        return $blocks;
    }

    /**
     * Собирает конкретный смысловой блок страницы
     * 
     * @param array $level
     *   Перебираемый уровень блоков
     * 
     * @return string $view
     *   Собранный смысловой блок
     * 
     */
    private function build_block($level) {
        $view = '';
        foreach ($level as $block) {
            foreach ($block as $content) {
                if (is_string($content)) {
                    $view .= $content;
                } elseif (is_array($content)) {
                    $child = $this->build_block($content);
                    $view = str_replace(self::PLACE_CHILD, $child, $view);
                }
            }
        }
        return $view;
    }

    /**
     * Собирает страницу из готовых смысловых блоков
     * 
     * @return string $view
     *   Готовые данные в виде строки
     * 
     */
    private function build_page() {
        $blocks = $this->blocks;

        // Добавляет данные в блоки
        $blocks_adding_data = $this->adding_data($blocks);

        // Сборка блоков
        $view_build_block = $this->build_block($blocks_adding_data);

        // Заменяет пути
        $view = $this->replace_paths($view_build_block);

        return $view;
    }

    /**
     * Заменяет пути к подключаемым файлам в соответствии с шаблоном
     * 
     * @param string $page
     *   Верстка у которой надо заменить пути
     * 
     */
    private function replace_paths($page) {
        $pattern = '~<(link|img).*(href|src)="(.*)?">~';
        $subject = $page;
        $matches = array();

        // Ищем ссылки
        preg_match_all($pattern, $subject, $matches);
        foreach ($matches[3] as $match) {
            $page = preg_replace('~' . $match . '~', TEMPLATE_PATH . '/' . $match, $page);
        }

        return $page;
    }

    /**
     * Сборка страницы
     * 
     */
    public function build() {
        echo $this->view;
    }

}
