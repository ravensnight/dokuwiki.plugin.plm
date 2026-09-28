<?php

enum Trigger {
    case none;      // nothing to do
    case reset;     // element deselected
    case select;    // element selected/loaded
    case change;    // element updated
}

class EventDetails implements JsonSerializable {
    public readonly ?int $id;
    public readonly Trigger $trigger;

    public function __construct(Trigger $trigger, ?int $id) {
        $this->id = $id;
        $this->trigger = $trigger;
    }

    #[Override]
    public function jsonSerialize(): mixed
    {
        return [            
            "id" => $this->id ?? null,
            "trigger" => $this->trigger->name
        ];
    }
}

class Event implements JsonSerializable {

    public string $name;
    public EventDetails $details;

    public function __construct(string $name, Trigger $trigger = Trigger::none, ?int $id = null)
    {
        $this->name = $name;
        $this->details = new EventDetails($trigger, $id);
    }

    public static function none(string $name): Event
    {
        return new Event($name);
    }

    public static function reset(string $name): Event
    {
        return new Event($name, Trigger::reset);
    }

    public static function select(string $name, int $id): Event
    {
        return new Event($name, Trigger::select, $id);
    }

    public static function change(string $name, int $id): Event
    {
        return new Event($name, Trigger::change, $id);
    }

    #[Override]
    public function jsonSerialize(): mixed
    {
        return [
            "name" => $this->name ?? null,
            "details" => $this->details->jsonSerialize()
        ];
    }
}