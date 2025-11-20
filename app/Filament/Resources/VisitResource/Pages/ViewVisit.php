<?php

namespace App\Filament\Resources\VisitResource\Pages;

use App\Filament\Resources\VisitResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;

class ViewVisit extends ViewRecord
{
    protected static string $resource = VisitResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [];

        // Edit Visit (only for active visits)
        if ($this->record->status === 'in_progress') {
            $actions[] = Actions\EditAction::make()
                ->icon('heroicon-o-pencil-square');
        }

        // View Client Profile
        $actions[] = Actions\Action::make('view_client')
            ->label('View Client Profile')
            ->icon('heroicon-o-user')
            ->color('info')
            ->url(route('filament.admin.resources.clients.view', $this->record->client_id))
            ->openUrlInNewTab();

        // STAGE-SPECIFIC ACTIONS

        // RECEPTION STAGE
        if ($this->record->current_stage === 'reception') {
            if ($this->record->service_available === 'yes') {
                $actions[] = Actions\Action::make('send_to_triage')
                    ->label('Send to Triage Queue')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Send to Triage')
                    ->modalDescription('Move this client to the triage queue?')
                    ->action(function () {
                        $this->record->completeStage();
                        $this->record->moveToStage('triage');
                        
                        Notification::make()
                            ->success()
                            ->title('Sent to Triage')
                            ->body("Visit {$this->record->visit_number} moved to triage queue")
                            ->send();
                        
                        return redirect()->route('filament.admin.resources.visits.view', $this->record);
                    })
                    ->button();
            }
        }

        // TRIAGE STAGE
        if ($this->record->current_stage === 'triage') {
            if (!$this->record->triage()->exists()) {
                $actions[] = Actions\Action::make('start_triage')
                    ->label('Start Triage Assessment')
                    ->icon('heroicon-o-heart')
                    ->color('info')
                    ->url(route('filament.admin.resources.triages.create', ['visit' => $this->record->id]))
                    ->button();
            } else {
                $actions[] = Actions\Action::make('view_triage')
                    ->label('View Triage Results')
                    ->icon('heroicon-o-heart')
                    ->color('gray')
                    ->url(route('filament.admin.resources.triages.view', $this->record->triage->id))
                    ->openUrlInNewTab();
            }
        }

        // INTAKE STAGE - KEY ACTIONS!
        if ($this->record->current_stage === 'intake') {
            if (!$this->record->intakeAssessment()->exists()) {
                // No intake completed yet - CREATE
                $actions[] = Actions\Action::make('complete_intake')
                    ->label('Complete Intake Assessment')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('primary')
                    ->url(route('filament.admin.resources.intake-assessments.create', ['visit' => $this->record->id]))
                    ->button()
                    ->size('lg');
            } else {
                // Intake exists - VIEW and EDIT options
                $actions[] = Actions\Action::make('view_intake')
                    ->label('View Intake')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(route('filament.admin.resources.intake-assessments.view', $this->record->intakeAssessment->id))
                    ->openUrlInNewTab();

                $actions[] = Actions\Action::make('edit_intake')
                    ->label('Edit Intake & Services')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->url(route('filament.admin.resources.intake-assessments.edit', $this->record->intakeAssessment->id));

                // Manual option to move to billing if needed
                $actions[] = Actions\Action::make('send_to_billing')
                    ->label('Send to Billing')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Move to Billing')
                    ->modalDescription('Move this visit to billing stage? Ensure intake is complete and services are selected.')
                    ->action(function () {
                        if (!$this->record->serviceBookings()->exists()) {
                            Notification::make()
                                ->danger()
                                ->title('No Services Selected')
                                ->body('Cannot move to billing. Please select at least one service in intake.')
                                ->persistent()
                                ->send();
                            return;
                        }

                        $this->record->completeStage();
                        $this->record->moveToStage('billing');
                        
                        Notification::make()
                            ->success()
                            ->title('Sent to Billing')
                            ->body("Visit {$this->record->visit_number} moved to billing")
                            ->send();
                        
                        return redirect()->route('filament.admin.resources.visits.view', $this->record);
                    })
                    ->button();
            }
        }

        // BILLING STAGE
        if ($this->record->current_stage === 'billing') {
            if (!$this->record->invoices()->exists()) {
                $actions[] = Actions\Action::make('create_invoice')
                    ->label('Generate Invoice')
                    ->icon('heroicon-o-document-text')
                    ->color('warning')
                    ->url(route('filament.admin.resources.invoices.create', ['visit' => $this->record->id]))
                    ->button()
                    ->size('lg');
            } else {
                $actions[] = Actions\Action::make('view_invoice')
                    ->label('View Invoice')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->url(route('filament.admin.resources.invoices.view', $this->record->invoices()->latest()->first()->id))
                    ->openUrlInNewTab();

                // Option to move to payment if invoice exists
                if ($this->record->invoices()->latest()->first()->status === 'pending_payment') {
                    $actions[] = Actions\Action::make('send_to_payment')
                        ->label('Send to Payment/Cashier')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function () {
                            $this->record->completeStage();
                            $this->record->moveToStage('payment');
                            
                            Notification::make()
                                ->success()
                                ->title('Sent to Payment')
                                ->body("Visit {$this->record->visit_number} moved to payment/cashier")
                                ->send();
                            
                            return redirect()->route('filament.admin.resources.visits.view', $this->record);
                        })
                        ->button();
                }
            }
        }

        // PAYMENT STAGE
        if ($this->record->current_stage === 'payment') {
            $invoice = $this->record->invoices()->latest()->first();
            
            if ($invoice && $invoice->status !== 'paid') {
                $actions[] = Actions\Action::make('process_payment')
                    ->label('Process Payment')
                    ->icon('heroicon-o-credit-card')
                    ->color('orange')
                    ->url(route('filament.admin.resources.payments.create', ['visit' => $this->record->id]))
                    ->button()
                    ->size('lg');
            } else {
                $actions[] = Actions\Action::make('view_payment')
                    ->label('View Payment Receipt')
                    ->icon('heroicon-o-document-check')
                    ->color('success')
                    ->url(route('filament.admin.resources.payments.index', ['visit' => $this->record->id]))
                    ->openUrlInNewTab();
            }
        }

        // SERVICE STAGE
        if ($this->record->current_stage === 'service') {
            $actions[] = Actions\Action::make('view_service_queues')
                ->label('View Department Queues')
                ->icon('heroicon-o-queue-list')
                ->color('success')
                ->url(route('filament.admin.resources.service-queues.index', ['visit' => $this->record->id]));

            $actions[] = Actions\Action::make('view_service_bookings')
                ->label('View Service Bookings')
                ->icon('heroicon-o-briefcase')
                ->color('info')
                ->url(route('filament.admin.resources.service-bookings.index', ['visit' => $this->record->id]));
        }

        // CHECKOUT (for any in-progress visit)
        if ($this->record->status === 'in_progress') {
            $actions[] = Actions\Action::make('checkout')
                ->label('Checkout Client')
                ->icon('heroicon-o-arrow-left-on-rectangle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Checkout Client')
                ->modalDescription('Are you sure you want to checkout this client? This will end their visit.')
                ->form([
                    \Filament\Forms\Components\Textarea::make('checkout_notes')
                        ->label('Checkout Notes')
                        ->rows(3)
                        ->placeholder('Any notes about the visit completion...'),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'check_out_time' => now(),
                        'status' => 'completed',
                        'current_stage' => 'completed',
                    ]);

                    // Complete current stage
                    $this->record->completeStage();

                    activity()
                        ->performedOn($this->record)
                        ->causedBy(auth()->user())
                        ->log("Visit checked out. Notes: " . ($data['checkout_notes'] ?? 'None'));
                    
                    Notification::make()
                        ->success()
                        ->title('Client Checked Out')
                        ->body("Visit {$this->record->visit_number} completed successfully")
                        ->send();
                    
                    return redirect()->route('filament.admin.resources.visits.view', $this->record);
                });
        }

        return $actions;
    }

    protected function getFooterWidgets(): array
    {
        return [
            VisitResource\Widgets\VisitStageTimeline::class,
            VisitResource\Widgets\VisitServicesOverview::class,
        ];
    }
}