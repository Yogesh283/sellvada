<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SellResource\Pages;
use App\Filament\Admin\Resources\SellResource\RelationManagers;
use App\Models\Sell;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SellResource extends Resource
{
    protected static ?string $model = Sell::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('buyer_id')->searchable(),
                Tables\Columns\TextColumn::make('sponsor_id')->searchable(),
                Tables\Columns\TextColumn::make('income_to_user_id')->searchable(),
                Tables\Columns\TextColumn::make('leg')->searchable(),
                Tables\Columns\TextColumn::make('product')->searchable(),
                Tables\Columns\TextColumn::make('amount')->searchable(),
                Tables\Columns\TextColumn::make('status')->searchable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),


                //`buyer_id`, `sponsor_id`, `income_to_user_id`, `leg`, `product`, `type`, `amount`, `income`, `income_type`, `level`, `order_no`, `status`, `details`, `created_at`, `updated_at`, `pay_out_status
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSells::route('/'),
            'create' => Pages\CreateSell::route('/create'),
            'edit' => Pages\EditSell::route('/{record}/edit'),
        ];
    }
}
