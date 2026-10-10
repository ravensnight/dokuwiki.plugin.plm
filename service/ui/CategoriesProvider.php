<?php

class CategoriesProvider extends Provider {

    public function provide(array $context) : array {
        $db = PlmDB::get();
        $res = [];

        $sel = (int)(isset($context['selectedCategory']) ? $context['selectedCategory'] : '1');
        $list = Category::fetchAll($db);

        foreach ($list as $cat) {
            $entry = [
                "id" => $cat->pk,
                "name" => $cat->name,
                "description" => $cat->description,
            ];

            if ($cat->pk === $sel) {
                $entry['selected'] = 'selected';
            }

            $res[] = $entry;
        }

        return $res;
    }
}
