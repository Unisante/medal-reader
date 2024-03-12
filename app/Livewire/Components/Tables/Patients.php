<?php

namespace App\Livewire\Components\Tables;

use App\Services\FHIRService;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRAddress;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRContactPoint;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRId;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRBundle;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRPatient;
use DCarbone\PHPFHIRGenerated\R4\PHPFHIRResponseParser;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Laravolt\Avatar\Facade as Avatar;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Patients extends Component
{

    public int $algorithm_id;
    public $patients;
    public $sliced_patients;
    public string $search;
    public int $page;
    public int $per_page = 10;
    public int $current_page = 1;
    public bool $first_page = true;
    public bool $last_page = false;
    public bool $has_more_pages = false;
    public int|null $last_item;
    public int|null  $first_item;
    public int $total = 0;
    public array $pagination_buttons;
    public string $class;
    public string $style;

    protected FHIRService $fhirService;

    public function boot(FHIRService $fhirService)
    {
        $this->fhirService = $fhirService;
    }

    public function mount($algorithm_id = 94)
    {
        $this->algorithm_id = $algorithm_id;

        $parser = new PHPFHIRResponseParser();

        // $patients = new FHIRPatient;
        $response = $this->fhirService->getPatientsFromRemoteFHIRServer();
        $patients = [];
        $this->pagination_buttons = [0];
        if ($response?->successful()) {
            /** @var FHIRBundle $patients_bundle */
            $patients_bundle = $parser->parse($response->json());

            foreach ($patients_bundle->getEntry() as $entry) {
                /** @var FHIRPatient $patient_resource */
                $patient_resource = $entry->getResource();
                /** @var FHIRId $id */
                $id = $patient_resource->getId()->getValue()->getValue();
                $name = $patient_resource->getName()[0];
                $given_name = $name->getGiven()[0];
                $family_name = $name->getFamily();
                $prefix = $name->getPrefix()[0] ?? '';
                $family_extension = $family_name->getExtension()[0] ?? '';
                /** @var FHIRAttachment $photo */
                $photo = $patient_resource->getPhoto();
                $avatar = !empty($photo) ? $photo->getUrl()[0] :
                    Avatar::create("$given_name $family_name")->toBase64();
                $gender = $patient_resource->getGender()->getValue()->getValue();
                /** @var FHIRContactPoint $phone */
                $phone = $patient_resource->getTelecom()[0]->getValue()->getValue()->__toString();
                /** @var FHIRAddress $address */
                $address = $patient_resource->getAddress()[0];
                $line = $address->getLine()[0]->getValue()->getValue();
                $postal = $address->getPostalCode();
                $city = $address->getCity();
                $date_of_birth = $patient_resource->getBirthDate()->getValue()->__toString();
                $diff = date_diff(date_create($date_of_birth), date_create());
                $age = $diff->format('%y');
                $deceased = $patient_resource->getDeceasedBoolean();
                $mrn = $patient_resource->getIdentifier()[0]->getValue()->getValue()->getValue();

                $patients[] = [
                    'id' => $id,
                    'name' => "$prefix $given_name $family_name $family_extension",
                    'avatar' => $avatar,
                    'gender' => $gender,
                    'phone' => $phone,
                    'line' => $line,
                    'city' => "$postal $city",
                    'date_of_birth' => $date_of_birth,
                    'age' => $age,
                    'deceased' => $deceased ?? false,
                    'mrn' => $mrn,
                ];
            }
            // $this->total = $patients_bundle->getTotal()->getValue()->getValue();
            $this->total = 138;
            $this->pagination_buttons = [range(1, 10)];
        }
        $this->patients = collect($patients);
        $this->sliced_patients = $this->patients->slice(0, $this->per_page);

        $this->last_page = max((int) ceil($this->total / $this->per_page), 1);
        $this->first_item = $this->total > 0 ? ($this->current_page - 1) * $this->per_page + 1 : null;
        $this->last_item = $this->total > 0 ? $this->first_item + $this->current_page * $this->per_page - 1 : null;
        $this->has_more_pages = $this->current_page < $this->last_page;
    }

    public function render()
    {
        return view('livewire.components.tables.patients');
    }

    public function search()
    {
        $searched_patients = $this->patients->search($this->search);
        $this->sliced_patients = $searched_patients->slice(0, $this->per_page);
        $this->class = "show";
        $this->style = "display: block;";
    }

    public function start($patient_id)
    {
        return redirect()->route('home.process', [
            'id' => $this->algorithm_id,
            'patient_id' =>  $this->patients->where('id', $patient_id)->first()['id']
        ]);
    }

    public function nextPage()
    {
        $this->current_page = $this->current_page + 1;
        $this->sliced_patients = $this->patients->slice(($this->current_page) * $this->per_page, $this->per_page);
        $this->first_item = $this->total > 0 ? ($this->current_page - 1) * $this->per_page + 1 : null;
        $this->last_item = $this->total > 0 ? $this->first_item + $this->per_page - 1 : null;
        $this->first_page = $this->current_page === 1;
        $this->class = "show";
        $this->style = "display: block;";
    }

    public function gotoPage($page)
    {
        if ($page <= 0) {
            $page = 1;
        }
        $this->current_page = $page;
        $this->sliced_patients = $this->patients->slice($this->current_page * $this->per_page, $this->per_page);
        $this->first_item = $this->total > 0 ? ($this->current_page - 1) * $this->per_page + 1 : null;
        $this->last_item = $this->total > 0 ? $this->first_item +  $this->per_page - 1 : null;
        $this->first_page = $this->current_page === 1;
        $this->class = "show";
        $this->style = "display: block;";
    }

    public function previousPage()
    {
        $this->current_page = $this->current_page - 1;
        if ($this->current_page <= 0) {
            $this->current_page = 1;
        }
        $this->sliced_patients = $this->patients->slice(($this->current_page) * $this->per_page, $this->per_page);
        $this->first_item = $this->total > 0 ? ($this->current_page - 1) * $this->per_page + 1 : null;
        $this->last_item = $this->total > 0 ? $this->first_item + $this->per_page - 1 : null;
        $this->first_page = $this->current_page === 1;
        $this->class = "show";
        $this->style = "display: block;";
    }
}
