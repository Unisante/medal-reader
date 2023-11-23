<?php

namespace App\Livewire\Components\Inputs;

use Livewire\Attributes\Rule;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class Numeric extends Component
{
    public $node_id;
    public $label;
    public $description;
    public $answers = [];
    public $answer;
    public array $node;
    public string $cache_key;

    #[Rule('required|numeric')]
    public $value;

    public function mount($node, $cache_key)
    {
        $this->node_id = $node['id'];
        $this->cache_key =$cache_key;
        // $this->label = $node['label'];
        // $this->description = $node['description'];
        // $this->answers = $node['answers'];
        // $this->node = Cache::get($cache_key)['full_nodes'][$this->node_id];
        // dd($this->node);
    }

    public function updatingValue($value)
    {
        foreach (Cache::get($this->cache_key)['full_nodes'][$this->node_id]["answers"] as $answer) {
            $result = intval($value);
            $answer_value = $answer['value'];
            $answer_values = explode(',', $answer_value);
            $minValue = intval($answer_values[0]);
            $maxValue = intval($answer_values[1] ?? $minValue);

            $answer_id = match ($answer['operator']) {
                'more_or_equal' => $result >= $minValue ? $answer['id'] : null,
                'less' => $result < $minValue ? $answer['id'] : null,
                'between' => ($result >= $minValue && $result < $maxValue) ? $answer['id'] : null,
                default => null,
            };
            if ($answer_id && $answer_id !== $this->answer) {
                $this->dispatch('nodeToSave', $this->node_id, $value, $answer_id, $this->answer);
                $this->answer = $answer_id;
                return;
            }
        }
        $this->dispatch('nodeToSave', $this->node_id, $value, null, null);
    }
    public function render()
    {
        return view('livewire.components.inputs.numeric');
    }
}
