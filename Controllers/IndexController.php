<?php


namespace Tan\ERP\Controllers;

use Tan\ERP\Entities\ArticleCategory;
use Tan\ERP\Entities\CompanyCustomer;
use Tan\ERP\Entities\CompanyLead;
use Tan\ERP\Entities\FakeArticle\GoodsCategory;
use Tan\ERP\Entities\FakeArticle\Service;
use Tan\ERP\Entities\PaymentMethod;
use Tan\ERP\Entities\Sales\Invoice;
use Tan\ERP\Entities\Sales\Channel;
use Tan\ERP\Entities\Sales\InvoiceFee;
use Tan\ERP\Entities\Sales\InvoiceItem;
use Tan\ERP\Entities\Sales\InvoiceTender;
use Tan\ERP\Entities\Unit;
use Tan\ERP\Entities\Webhook;
use Tan\ERP\Events\WebhookRequestCreateEvent;
use Tan\ERP\Events\WebhookRequestDeleteEvent;
use Tan\ERP\Events\WebhookRequestUpdateEvent;
use Tan\ERP\Support\Facade;
use App\Mail\Tender\UserSendInvoice;
use App\Models\Company;
use App\Models\Tender\Invoice\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class IndexController extends Controller
{
    /**
     * Handles webhook that comes from ERP
     *
     * {app}/erp/webhook?c={CLIENT}&s={SECRET}&a={ACTION:created|updated|deleted}
     *
     * EXAMPLE: https://box1.Tan.com/erp/webhook?c=Tan&s=SECRET
     *
     * @param Request $request
     * @throws \Exception
     * @return string
     */
    public function webhook(Request $request)
    {
        if (empty($request->get('c')) || empty($request->get('s')) ||
            !in_array($request->get('a'), ['created', 'updated', 'deleted']) ||
            $request->get('c') !== Config::get('erp.webhook.client') ||
            $request->get('s') !== Config::get('erp.webhook.secret')) {
            Log::channel('erp')->error("Invalid action and/or client and/or secret!", ['request' => $request]);
            abort(403);
        }

        // WORKAROUND: ERP doesn't care about headers.. who cares?
        $entityId = $request->json('entityId');
        $entityName = $request->json('entityName');

        info($request->all());

        switch ($request->get('a')) {
            case 'deleted':
                $event = new WebhookRequestDeleteEvent($entityId, $entityName);
                break;
            case 'created':
                $event = new WebhookRequestCreateEvent($entityId, $entityName);
                break;
            case 'updated':
                $event = new WebhookRequestUpdateEvent($entityId, $entityName);
                break;
            default:
                throw new \Exception('Not supported yet!');
        }

        Event::dispatch($event);

        return 'OK';
    }


    /**
     * Manual testing API calls that come from AGR
     *
     * @param Request $request
     * @param string $case
     * @throws \Tan\ERP\Exceptions\ApiErrorException
     * @throws \Tan\ERP\Exceptions\ApiErrorException
     * @return string
     */
    public function api(Request $request, $case)
    {
        $id = $request->get('id');
        $this->$case($id);

        return 'OK';
    }

    protected function test_init()
    {
        \Tan\ERP\Support\Facade::initEnvironmentForAGR();
    }


    protected function test_invoice_pdf()
    {
        $model = \App\Models\Tender\Invoice::find(12);
        Mail::send(new UserSendInvoice($model->author, $model));
    }


    protected function test_invoice_for_service_paid()
    {
        $mInvoice = \App\Models\Tender\Invoice::find(5);
        $mInvoice->status_id = \App\Models\Tender\Invoice\Status::STATUS_PAID;
        $mInvoice->save(); // payed
        dd(1);
    }

    protected function test_invoice_for_service_create()
    {
        $mInvoice = \App\Models\Tender\Invoice::find(4);

        $invoice = new InvoiceFee();
        $invoice->fillFromModel($mInvoice);
        $invoice->save();

        $invoice->addInvoicePaidComment();

        dd($invoice);
    }

    protected function test_invoice_for_tender_create()
    {
        dd(1);
    }

    protected function test_SalesChannel()
    {
        $items = Channel::all();
        dd($items);
    }

    protected function test_invoice_query()
    {
        $c = Invoice::query()->where('invoiceNumber', 'like', 'aaa%')->count();
        dd($c);
    }

    protected function test_invoice_all()
    {
        dd(Invoice::all());
    }

    protected function test_user_UserDeleted($id)
    {
        $user = User::find($id);
        $user->delete();
    }

    protected function test_user_UserVerified($id)
    {
        /*
         * 1. admin creates/changes user who is verified
         * 2. ERP observer catches event and created sync job for user model
         * 3. job checks user model and guesses what to do - update, create, delete
         *  - if new user or not synced yet - create new contact on ERP
         *  - if user not exist - try to delete on ERP
         *  - if user exist - update on ERP
         */
        $user = User::find($id);
        $user->surname = Str::random();// 'test surname';
        $user->save();
    }
}
