<?php

namespace App\Filament\Admin\Resources\DepositResource\Pages;

use App\Filament\Admin\Resources\DepositResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditDeposit extends EditRecord
{
    protected static string $resource = DepositResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),

            // Approve action
            Actions\Action::make('approve')
                ->label('Approve')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status === 'pending')
                ->action(function () {
                    $this->record->update([
                        'status' => 'approved',
                        'approved_by' => auth()->id(),
                        'approved_at' => now(),
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Deposit approved')
                        ->body('Deposit #'.$this->record->id.' approved.')
                        ->send();

                    // reload the page so form shows updated state
                    $this->redirect($this->getUrl());
                }),

            // Reject action
            Actions\Action::make('reject')
                ->label('Reject')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status === 'pending')
                ->action(function () {
                    $this->record->update([
                        'status' => 'rejected',
                        'approved_by' => auth()->id(), // or null/other field if you prefer
                        'approved_at' => now(),
                    ]);

                    Notification::make()
                        ->warning()
                        ->title('Deposit rejected')
                        ->body('Deposit #'.$this->record->id.' rejected.')
                        ->send();

                    $this->redirect($this->getUrl());
                }),
        ];
    }
}
