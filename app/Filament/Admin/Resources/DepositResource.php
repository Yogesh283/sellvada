<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DepositResource\Pages;
use App\Models\Deposit;
use Filament\Forms\Form;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Str;

class DepositResource extends Resource
{
    protected static ?string $model = Deposit::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Card::make()
                    ->schema([
                        Select::make('user_id')
                            ->label('User')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->required(),

                        TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->placeholder('Enter amount'),

                        Select::make('method')
                            ->label('Method')
                            ->options([
                                'Cash'  => 'Cash',
                                'bank'  => 'Bank Transfer',
                                'upi'   => 'UPI',
                                'wallet'=> 'Wallet',
                                'card'  => 'Card',
                            ])
                            ->required(),

                        TextInput::make('reference')
                            ->label('Reference')
                            ->nullable(),

                        TextInput::make('note')
                            ->label('Note')
                            ->nullable(),

                        TextInput::make('receipt_path')
                            ->label('Receipt Path')
                            ->nullable(),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending'  => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->default('pending')
                            ->required(),

                        DateTimePicker::make('approved_at')
                            ->label('Approved At')
                            ->nullable(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable()->searchable(),

                TextColumn::make('user.name')
                    ->label('User')
                    ->limit(30)
                    ->searchable(),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('INR', true),

                TextColumn::make('method')
                    ->label('Method')
                    ->formatStateUsing(fn ($state) => $state ? Str::title($state) : $state),

                BadgeColumn::make('status')
                    ->label('Status')
                    // convert DB value -> display label
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        default => $state,
                    })
                    // color keys map to value names — keeps badge color logic
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger'  => 'rejected',
                    ]),

                TextColumn::make('reference')->label('Reference')->limit(20),

                TextColumn::make('created_at')->label('Created')->dateTime()->sortable(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDeposits::route('/'),
            'create' => Pages\CreateDeposit::route('/create'),
            'edit'   => Pages\EditDeposit::route('/{record}/edit'),
        ];
    }
}
