<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ItemCollection;
use App\Entity\Location;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Google\Service\Sheets;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google_Client;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class SheetService
{

    public function __construct(
        private EntityManagerInterface $entityManager,
        private PropertyAccessorInterface $accessor,
        private ?Google_Client $googleClient=null,
                                private ?Sheets $googleSheetsService = null,
                                #[Autowire('%env(JSON_AUTH)%')] string $jsonAuth=null)
    {
        $client =  new Google_Client();
//        $client->setApplicationName('Google Sheets API');
        $client->setScopes([Sheets::SPREADSHEETS]);
        $client->setAccessType('offline');
        $this->googleClient = $client;
        if ($jsonAuth) {
            $jsonData =  file_exists($jsonAuth) ? file_get_contents($jsonAuth) : $jsonAuth;
            $jsonAuth = json_decode($jsonData, true);
            if ($jsonAuth) {
                $this->init($jsonAuth);
            }
        }
    }

    public function importGoogleSheet(Project $project)
    {
        // populate $project with data from Google Sheets
        $service = new Sheets($this->googleClient);

        $spreadsheet = $service->spreadsheets->get($spreadsheetId = $project->getGoogleSheetsId());
        $accessor = new PropertyAccessor();

        foreach ($spreadsheet->getSheets() as $sheet) {
            $sheetName = $sheet->getProperties()->getTitle();
            $range = $sheetName; // here we use the name of the Sheet to get all the rows
            $response = $service->spreadsheets_values->get($spreadsheetId, $range);
            $values = $response->getValues();
            switch ($sheetName) {

                case '#loc':
                    foreach ($values as $idx => $row) {
                        if ($idx === 0) {
                            $keys = $row;
                            continue;
                        }
                        if (count($row) == count($keys)) {
                            $row = array_combine($keys, $row);
                            $related = $this->getRelated($row, Location::class, $project);
                        }
                    }
                    break;
                case '_p':
                    foreach ($values as $row) {
                        [$field, $value] = $row;
                        $accessor->setValue($project, $field, $value);
                    }
                    break;
                default:

                    // it's a collection...

            }
        }
    }

    private function populateObject(array $row, Location $object)
    {

//        $object->translate('es')->setLabel($row['label.es']);
//        $object->translate('en')->setLabel($row['label.en']);
////        $object->translate('fr')->setLabel('xxx');
//        foreach ($row as $field => $value) {
//            if (str_contains($field, '.')) {
//                [$field, $locale] = explode('.', $field);
//                dump(field: $field, locale: $locale,  value: $value);
//            }
//        }
////
////// In order to persist new translations, call mergeNewTranslations method, before flush
//        $object->mergeNewTranslations();
//        $this->entityManager->flush();
//        dd($object);
////
////        dump($object->translate('en')->getLabel());
////        dump($object->translate('fr')->getLabel());
//        return;

        foreach ($row as $field => $value) {
            if (str_contains($field, '.')) {
                [$field, $locale] = explode('.', $field);
                dump(locale: $locale);
                $object->translate($locale)->setLabel($value);
                dump("setting $field to $value for $locale", transLocale: $object->translate($locale)->getLocale());

//                match($field) {
//                    'label' => $object->translate($locale)->setLabel($value, $locale),
//                    'description' => $object->translate($locale)->setDescription($value, $locale)
//                };

//                $translationEntity = $object->translate($locale);
//                assert($translationEntity->getLocale() === $locale, "$locale <> " . $translationEntity->getLocale());
//                dump(te: $translationEntity, locale: $locale);
//                match($field) {
//                    'label' => $translationEntity->setLabel($value, $locale),
//                    'description' => $translationEntity->setDescription($value, $locale)
//                };
//                $this->accessor->setValue($translationEntity, $field, $value);
//                dump($translationEntity, $locale, $value);
            } else {
//                $this->accessor->setValue($object, $field, $value);
            }
        }

        $object->mergeNewTranslations();
        $object->getTranslations()->map(fn($t) => dump($t));
//        dd($row, $object, $object->getTranslations()->count());

    }

    private function getRelated(array $row, string $class, Project $project): Location|ItemCollection
    {
        $code = $row[AppService::CODE_COLUMN];
        $repo = $this->entityManager->getRepository($class);
        /** @var $entity Location */
        if (!$entity = $repo->findOneBy(['project' => $project, 'code' => $code])) {
            $entity = new $class;
            $entity->setCode($code);
            $entity->setProject($project);
            $entity->setLocale($project->getLocale());
            $entity->setCurrentLocale($project->getLocale());
            $this->entityManager->persist($entity);
        }
        $this->populateObject($row, $entity);
        return $entity;


    }

    /*
* The JSON auth file can be provided to the Google Client in two ways, one is as a string which is assumed to be the
* path to the json file. This is a nice way to keep the creds out of the environment.
*
*/
    public function init(array $jsonAuth): void
    {
        $client = $this->googleClient;
        $client->setApplicationName('JUFJ'); // ??
//        $client->setScopes([\Google_Service_Sheets::DRIVE]);
        $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
        $client->setAccessType('offline');
        $client->setAuthConfig($jsonAuth);

        $this->googleSheetsService = new \Google_Service_Sheets($this->googleClient);
    }


    public function updateCell(string $spreadsheetId, $cell, array $updateRow, string $sheetId=null)
    {
//        $client = $this->googleClient;
//        $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);

        $rows = [$updateRow];
        $valueRange = new \Google_Service_Sheets_ValueRange();
        $valueRange->setValues($rows);
        $range = $cell; // where the replacement will start, here, first column and second line
        $options = ['valueInputOption' => 'USER_ENTERED'];
        $this->googleSheetsService->spreadsheets_values->update($spreadsheetId, $range, $valueRange, $options);

    }
    public function getData(string $spreadsheetId, callable $function)
    {
        $client = $this->googleClient;
        $client->setApplicationName('Google Sheets API');
        $client->setScopes([Sheets::SPREADSHEETS]);
        $client->setAccessType('offline');
// credentials.json is the key file we downloaded while setting up our Google Sheets API
//        $path = $this->dataDir . '/../google-credentials.json';
//        $client->setAuthConfig($path);

// configure the Sheets Service
        $service = new Sheets($client);

//        https://docs.google.com/spreadsheets/d/1mcOvia45gTzlMXlp9zF0o7ahbkKZa5AHfym7XiM5AL4/edit#gid=0
//        $spreadsheetId = '1BK17HWOkAC8XOa0uGlAmOMQvI2F5DxhKRkhgg17yK2Q';
        $spreadsheet = $service->spreadsheets->get($spreadsheetId);
        foreach ($spreadsheet->getSheets() as $sheet) {
            $sheetName = $sheet->getProperties()->getTitle();
            $range = $sheetName; // here we use the name of the Sheet to get all the rows

            // @todo: make this a parameter
            $options = [
//                'valueRenderOption' => 'FORMULA'
            ];
            $response = $service->spreadsheets_values->get($spreadsheetId, $range, $options);
            $values = $response->getValues();
            $function($values, $sheet);
        }

    }

    public function getOrCreateSheet(Project $project, $tabName)
    {
        try {
            // use new name
            $body = new BatchUpdateSpreadsheetRequest(array(
//            $body = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest(array(
                'requests' => array(
                    'addSheet' => array(
                        'properties' => array(
                            'title' => $tabName
                        )
                    )
                )
            ));
            $service = new \Google_Service_Sheets($this->googleClient);
            $result1 = $service->spreadsheets->batchUpdate($project->getGoogleSheetsId() ,$body);
        } catch(\Exception $exception) {
            dd($exception);
        }

        $body = new Google_Service_Sheets_ValueRange([
            'values' => $values
        ]);
        $params = [
            'valueInputOption' => $valueInputOption
        ];
        //executing the request
        $result = $service->spreadsheets_values->update($spreadsheetId, $range,
            $body, $params);
        printf("%d cells updated.", $result->getUpdatedCells());
        return $result;
    }
}
