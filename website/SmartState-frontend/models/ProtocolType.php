<?php
require_once __DIR__ . '/../utilities/db.php';
require_once __DIR__ . '/../utilities/UUID.php';

class ProtocolType {
    protected $uuid;
    protected $name;

    public function __construct() {
        $this->uuid = UUID::v4();
        $this->name = null;
    }

    public static function create($name): ?ProtocolType {
        if (empty($name))
            throw new Exception('You must provide a name to create a protocol type');
        $instance = new self();
        $instance->setName($name);
        try {
            $newProtocol = $instance->save();
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage( ) , $e->getCode( ));
        }
        return $newProtocol;
    }

    public static function delete($id): bool {
        if (empty($id) || is_null($id))
            throw new Exception('ID for protocol type not found.');
        $instance = new self();
        $object = $instance->withID($id);
        $name = $object->getName();
        $status = $instance->deleteFromID($id);
        $isDeleted = $instance->deleteParticipantGroup($name);
        if (!$isDeleted) {
            throw new PDOException('Could not delete participant group.');
        }
        return $status;
    }

    public static function update($uuid, $name): ProtocolType {
        if (empty($uuid))
            throw new Exception('You must provide an id to update a protocol');
        $instance = ProtocolType::withID($uuid);
        if (is_null($instance))
            throw new Exception("No protocol found with id [{$uuid}]");
        $oldName = $instance->getName();
        $instance->setName($name);

        try {
            $updatedProtocol = $instance->save();
            $instance->updateParticipantGroup($oldName);
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage(), $e->getCode());
        }
        return $updatedProtocol;
    }

    protected function updateParticipantGroup($oldName): void {
        $newName = $this->getName();
        $stmt = DB::run("UPDATE participants SET participant_json=JSON_MODIFY(participant_json, '$.group', ?) WHERE JSON_VALUE(participant_json, '$.group') = ?",
        [$newName, $oldName]);
    }

    protected function deleteParticipantGroup($oldName): bool {
        $newName = 'UNASSIGNED';
        $stmt = DB::run("UPDATE participants SET participant_json=JSON_MODIFY(participant_json, '$.group', ?) WHERE JSON_VALUE(participant_json, '$.group') = ?",
        [$newName, $oldName]);
        if ($stmt->rowCount() > 0)
            return true;
        else
            return false;
    }

    public static function all(): array {
        $types = [];
        $query = "SELECT * FROM protocol_types";
        $stmt = DB::run($query);
        while ($row = $stmt->fetch(PDO::FETCH_LAZY))
            array_push($types, ProtocolType::withRow($row));
        return $types;
    }

    public static function countForDatatable(): int {
        $stmt = DB::run("SELECT count(protocol_type_uuid) FROM protocol_types");
        $count = $stmt->fetchColumn();
        return $count;
    }

    public static function countFilteredForDatatable(string $filter): int {
        $query = "SELECT count(protocol_type_uuid) FROM protocol_types";
        if (!is_null($filter) && strlen($filter) > 0) {
            $filter = "%{$filter}%";
            $query .= " WHERE (name LIKE :filter)";
        }
        $stmt = DB::prepare($query);
        if (!is_null($filter) && strlen($filter) > 0) {
            $stmt->bindParam('filter', $filter);
        }
        $stmt->execute();
        $count = $stmt->fetchColumn();
        return $count;
    }

    public static function listForDatatable(int $start, int $length, string $order_by, string $order_dir, string $filter): array {
        $matches = [];
        $query = "SELECT * FROM protocol_types";
        $prune = '';
        if ($length > 0)
            $prune .= " OFFSET {$start} ROWS FETCH NEXT {$length} ROWS ONLY";
        if (is_null($order_by) || $order_by == '' || $order_by == '0')
            $order_by = 'name';
        if (is_null($order_dir) || $order_dir == '')
            $order_dir = 'desc';
        if (!is_null($filter) && strlen($filter) > 0) {
            $filter = '%' . $filter . '%';
            $query .= " WHERE (name LIKE :filter)";
        }
        $query .= " ORDER BY {$order_by} {$order_dir}{$prune}";
        $stmt = DB::prepare($query);
        if (!is_null($filter) && strlen($filter) > 0) {
            $stmt->bindValue('filter', $filter);
        }
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_LAZY))
            array_push($matches, ProtocolType::withRow($row));
        return $matches;
    }

    public static function withID( $id ): ?ProtocolType {
        try {
            $instance = new self();
            $instance->loadByID($id);
            return $instance;
        } catch (PDOException $e) {
            return null;
        }
    }

    public static function withName( $name ): ?ProtocolType {
        try {
            $instance = new self();
            $instance->loadByName($name);
            return $instance;
        } catch (PDOException $e) {
            return null;
        }
    }

    public static function withRow( $row ): ProtocolType {
        $instance = new self();
        $instance->fill( $row );
        return $instance;
    }

    protected function loadByID( $id ): void {
        $stmt = DB::run(
            "SELECT * FROM protocol_types WHERE protocol_type_uuid = :id ORDER BY protocol_type_uuid OFFSET 0 ROWS FETCH NEXT 1 ROWS ONLY",
            ['id' => $id]
        );
        $row = $stmt->fetch(PDO::FETCH_LAZY);
        if ($row <> null)
            $this->fill( $row );
        else
            throw new PDOException("Protocol with id [{$id}] not found");
    }

    protected function fill( $row ): void {
        $this->uuid = $row['protocol_type_uuid'];
        $this->name = $row['name'];
    }

    protected function loadByName( $name ): void {
        $stmt = DB::run(
            "SELECT * FROM protocol_types WHERE name = :name ORDER BY name OFFSET 0 ROWS FETCH NEXT 1 ROWS ONLY",
            ['name' => $name]
        );
        $row = $stmt->fetch(PDO::FETCH_LAZY);
        if ($row <> null)
            $this->fill( $row );
        else
            throw new PDOException("Protocol with id [{$name}] not found");
    }

    protected function save(): ?ProtocolType {
        $exists = ProtocolType::withID($this->getID());
        if (is_null($exists)) {
            $insert = "INSERT INTO protocol_types (protocol_type_uuid, name) VALUES (?,?)";
            DB::run($insert, [$this->getID(), $this->getName()]);
        } else {
            $update = "UPDATE protocol_types SET name=? WHERE protocol_type_uuid=?";
            DB::run($update, [$this->getName(), $this->getID()]);
        }
        return ProtocolType::withID($this->getID());
    }

    protected function deleteFromID( $id ): bool {
        $stmt = DB::prepare("DELETE FROM protocol_types WHERE protocol_type_uuid = :id");
        $stmt->bindParam('id', $id);
        $stmt->execute();
        if ($stmt->rowCount() > 0) 
            return true;
        else
            return false;
    }

    /**
     * @return string
     */
    public function getID(): string {
        return $this->uuid;
    }

    /**
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void {
        $this->name = $name;
    }

    /**
     * @return array|mixed
     */
    public function jsonSerialize(): array {
        return [
            'id'        => $this->getID(),
            'name'      => $this->getName()
        ];
    }
}