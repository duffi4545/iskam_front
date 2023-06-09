<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Presenters\templates\security\MyAuthenticator;
use Nette;
use Nette\Application\UI\Form;
use Nette\Http\FileUpload;


final class HomePresenter extends Nette\Application\UI\Presenter
{
    /**
     * Food manager
     *
     * @var \FoodManager
     * @inject
     */
    public $foodManager;

    public $issueManager;
    public $api;
    private $authenticator;

    public function __construct(MyAuthenticator $authenticator)
    {
        $this->authenticator = $authenticator;
    }

    function setApi($api)
    {
        $this->api = $api;
    }

    public function beforeRender()
    {
        $this->redrawControl("flashMessages");
    }

    private function getUploadPath(): string
    {
        return __DIR__ . '/../../www/uploads';
    }


    public function renderDefault(): void
    {
        $alergen = $this->foodManager->getAlergens();
        $request = $this->foodManager->getAllCategory();
        $this->template->requestts=($request);
        $classis = [];
        $pocetAlergenu = count($alergen->body);

        for ($i = 0; $i < $pocetAlergenu / 6; $i++) {
            $classis[] = "bg-primary";
            $classis[] = "bg-secondary";
            $classis[] = "bg-success";
            $classis[] = "bg-danger";
            $classis[] = "bg-warning text-dark";
            $classis[] = "bg-info";
        }

        if (!isset($this->template->foods) || !isset($this->template->actualPage) || !isset($this->template->pages)) {
            $this->readDataWithFilter(null);
        }

        if (!isset($this->template->alergens)) {
            $this->template->alergens = $alergen->body;
        }

        if (!isset($this->template->spanClass)) {
            $this->template->spanClass = $classis;
        }


    }


    public function processForm(Form $form, array $values): void
    {
        if ($form->isSuccess()) {

            /** @var FileUpload $fileUpload */
            $fileUpload = $values['itemImage'];


            $parser = !empty($values['itemIngredients_Id']) ? explode(';', substr_replace($values['itemIngredients_Id'], "", -1)) : array();


            $body = [];
            $body['name'] = $values['itemName'];
            $body['description'] = $values['itemDescription'];
            $body['price'] = $values['itemPrice'];
            $body['categoryId'] = $values["itemCategory"];
            $body['ingredients'] = $parser;

            $error = false;
            if (($fileUpload->isOk() && $fileUpload->isImage())) {

                if ($this->saveFile($fileUpload)) {
                    $body['image'] = isset($values['itemImage']) ? $fileUpload->name : null;

                } else {
                    $this->flashMessage("Soubor se nepodařilo uložit", "error");

                }
            }

            if (!$error) {
                if (isset($values['id']) && ($values['id'] != "")) {

                    $response = $this->foodManager->editFood($body, $values['id']);
                } else {
                    $response = $this->foodManager->createFood($body);
                }


                if (!$response->hasErrors()) {
                    $this->flashMessage("Pokrm byl úspěšně uložen", "success");
                } else {
                    $this->flashMessage("Data se nepodařilo užloit", "error");
                }
            }

        }


        $this->readDataWithFilter(null);

    }


    public function saveFile(FileUpload $file): bool
    {
        $uploadPath = $this->getUploadPath();

        $fileName = $file->name;
        $file->move($uploadPath . '/' . $fileName);

        return file_exists($uploadPath . "/" . $fileName);
    }

    public function actionLogin(string $username, string $password): void
    {
        try {
            $identity = $this->authenticator->authenticate($username, $password);
            $this->getUser()->login($identity);
            $this->redirect('this'); // nebo jiná stránka po úspěšném přihlášení
        } catch (Nette\Security\AuthenticationException $e) {
            $this->flashMessage('Neplatné přihlašovací údaje.', 'error');
            $this->redirect('this'); // nebo jiná stránka po úspěšném přihlášení
        }
    }

    public function actionOut(): void
    {
        $this->getUser()->logout(true);
        $this->redirect('home:default');
    }


    public function handleDelete($id)
    {
        $request = $this->foodManager->deleteFoodById($id);

        if($request->hasErrors()){
            $this->flashMessage("Jídlo se nepodařilo smazat", "error");
        }else{
            $this->flashMessage("Jídlo úspěšně smazáno", "success");
        }


        $this->redrawControl("foods");
        $this->payload->postGet = true;
        $this->payload->url = $this->link('this');
    }


    public function handleFilterFormSubmitted(Form $form, $values)
    {
        $this->readDataWithFilter($values);
    }

    public function handleRead(int $page)
    {
        $this->readDataWithFilter(null);
    }

    public function handleEdit()
    {
        $id = $_POST['id'];
        $request = $this->foodManager->getFoodById($id);
        $this->sendJson($request->body);
    }

    public function readDataWithFilter($values): void
    {

        if (isset($values)) {
            $parameters = $values;
        } else {
            $parameters = $this->getParameters();
        }


        $parser = !empty($parameters['itemIngredientsFilter_Id']) ? explode(';', substr_replace($parameters['itemIngredientsFilter_Id'], "", -1)) : array();

        $page = 1;
        if (isset($parameters['page'])) {
            $page = $parameters['page'];
        }
        $body = [];
        if (isset($parameters['name']) || isset($parameters['categoryFilter']) || isset($parameters['itemIngredientsFilter_Id']) || isset($parameters['sort'])) {

            $body['name'] = is_null($parameters['name']) ? "" : $parameters['name'];
            ($parameters['categoryFilter'] != "empty") ? $body['categoryId'] = $parameters['categoryFilter'] : null;
            $body['ingredientIdsExclude'] = $parser;
            $body['orderBy'] = is_null($parameters['sort']) ? "" : $parameters['sort'];
        }


        $request = $this->foodManager->getDataWithFilter($body, $page);


        if ($request->hasErrors()) {
            $this->template->foods = [];
            $this->template->foods = 0;
            $this->template->pages = 0;
        } else {


            $this->template->foods = $request->body;
            $this->template->pages = $request->headers['X-Total-Pages'];
            $this->template->actualPage = $page;


        }
        $this->redrawControl('foods');
        $this->redrawControl("pages");
    }

    protected function createComponentLoginForm(): Form
    {
        $form = new Form();

        $form->addText('username', 'Uživatelské jméno:')
            ->setRequired('Zadejte prosím uživatelské jméno.');

        $form->addPassword('password', 'Heslo:')
            ->setRequired('Zadejte prosím heslo.');

        $form->addSubmit('login', 'Přihlásit se');

        $form->onSuccess[] = function (Form $form, array $values) {
            $this->actionLogin($values['username'], $values['password']);
        };

        return $form;
    }

    protected function createComponentAddFoodForm(): Form
    {
        $request = $this->foodManager->getAllCategory();

        $categories = [];

        if (!$request->hasErrors()) {
            foreach ($request->body as $category) {
                $categories[$category->id] = $category->name;
            }

        }
        $form = new Form();
        $form->addText('itemName', 'Item Name')
            ->setRequired();
        $form->addTextArea('itemDescription', 'Item Description')
            ->setRequired();
        $form->addText('itemPrice', 'Price')
            ->setRequired()
            ->addRule(Form::FLOAT, 'Zadejte číslo')
            ->addRule(Form::MIN, 'Price must be greater than 0', 0);
        $form->addSelect('itemCategory', 'Category ID', $categories)
            ->setRequired();
        $form->addUpload('itemImage', 'Image File')
            ->setRequired()
            ->addRule(Form::MIME_TYPE, 'Please upload an image', ['image/jpeg', 'image/png']);
        $form->addText('itemIngredients', 'Ingredients (separated by commas)');

        $form->addHidden('itemIngredients_Id', '');
        $form->addHidden('id', '');

        $form->addSubmit('submit', 'Submit');

        $form->onSuccess[] = [$this, 'processForm'];

        return $form;
    }

    protected function createComponentEditFoodForm(): Form
    {
        $request = $this->foodManager->getAllCategory();

        $categories = [];

        if (!$request->hasErrors()) {
            foreach ($request->body as $category) {
                $categories[$category->id] = $category->name;
            }

        }
        $form = new Form();
        $form->addText('itemName', 'Item Name')
            ->setRequired();
        $form->addTextArea('itemDescription', 'Item Description')
            ->setRequired();
        $form->addText('itemPrice', 'Price')
            ->setRequired()
            ->addRule(Form::FLOAT, 'Zadejte číslo')
            ->addRule(Form::MIN, 'Price must be greater than 0', 0);
        $form->addSelect('itemCategory', 'Category ID', $categories)
            ->setRequired();
        $form->addUpload('itemImage', 'Image File')
            ->addRule(Form::MIME_TYPE, 'Please upload an image', ['image/jpeg', 'image/png']);
        $form->addText('itemIngredients', 'Ingredients (separated by commas)');

        $form->addHidden('itemIngredients_Id', '');
        $form->addHidden('id', '');

        $form->addSubmit('submit', 'Submit');

        $form->onSuccess[] = [$this, 'processForm'];

        return $form;
    }

    public function createComponentFilterForm(): Form
    {
        $form = new Form();

        $sort = [
            "" => "",
            "name:asc" => "name vzestupně ",
            "price:asc" => "price vzestupně",
            "name:desc" => "name sestupně",
            "price:desc" => "price sestupně",
        ];

        $request = $this->foodManager->getAllCategory();

        $categories = [];

        if (!$request->hasErrors()) {
            $categories["empty"] = "";
            foreach ($request->body as $category) {
                $categories[$category->id] = $category->name;
            }
        }

        $form->setMethod('get'); // Nastavení metody na GET
        $form->setAction(''); // Ponechání prázdné akce pro odeslání na stejnou stránku

        $form->addText('name', 'hledat')
            ->setHtmlAttribute('placeholder', 'Vyhledat název');
        $form->addHidden('itemIngredientsFilter_Id');
        $form->addSelect('categoryFilter', 'Category ID', $categories);

        $form->addSelect('sort', 'Sort', $sort)
            ->setDefaultValue("");

        $form->addSubmit('filter', 'Filtrovat');

        $form->onSuccess[] = [$this, 'handleFilterFormSubmitted'];

        return $form;

    }
}
