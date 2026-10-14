<?php

class PlmMgrParts extends PlmMgrBase
{

    #[Override]
    public function getData(array $params): ?array {
        $db = PlmDB::get();
        $key = PlmMgrBase::id($params);

        if ($key === null) {
            /** @var Part[] $parts */
            $parts = Part::entries($db);

            $result = [];
            foreach ($parts as $p) {
                $result[] = $this->createPartData($p);
            }
        } else {
            $p = Part::byPk($db, $key);
            if ($p === null) {
                return null;
            }
            return $this->createPartData($p);
        }

        return $result;
    }

    /**
     * Creates an array representation of a part
     *
     * @param Part $p
     * @return array
     */
    private function createPartData($p): array
    {
        return [
            "id" => $p->pk,
            "ipn" => $p->ipn,
            "description" => $p->description,
            "categoryId" => $p->categoryId
        ];
    }

    /**
     * Create a new part
     *
     * @param array $data
     * @return Part
     */
    #[Override]
    public function create(array $data): array  {
        $db = PlmDB::get();

        // Validate required fields
        if (!isset($data['ipn']) || trim($data['ipn']) === '') {
            throw new Exception('IPN is required');
        }

        // Fetch the category.
        $cat = Category::byPK($db, $data['categoryId']);
        if (!$cat) {
            throw new Exception("Category not found for id " . $data['categoryId']);
        }

        // create part
        $part = Part::createNew($cat, $data['ipn'], $data['description'] ?? '');
        $part->save($db);

        return $this->createPartData($part);
    }

    /**
     * Update an existing part
     *
     * @param int $id
     * @param array $data
     * @return Part
     */
    #[Override]
    public function update(array $data): array
    {
        $db = PlmDB::get();

        // Find the part
        $part = Part::byPk($db, $data['id']);
        if ($part === null) {
            throw new Exception('Part not found for id: ' . $data['id']);
        }

        // Validate required fields
        if (!isset($data['ipn']) || trim($data['ipn']) === '') {
            throw new Exception('IPN is required');
        }

        // Update the part
        $part->ipn = $data['ipn'];
        $part->description = $data['description'] ?? '';
        $part->categoryId = $data['categoryId'] ?? 1;

        // Save to database
        $part->save($db);
        return $this->createPartData($part);
    }
}
