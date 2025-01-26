<?php

namespace App\Filament\Resources;

use App\Exports\StudentsExport;
use App\Filament\Resources\StudentResource\Pages;
use App\Filament\Resources\StudentResource\RelationManagers;
use App\Models\Classes;
use App\Models\Section;
use App\Models\Student;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Maatwebsite\Excel\Facades\Excel;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'Academic Management';

    public static function getNavigationBadge(): ?string
    {
        return static::$model::count();
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->autofocus()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord:true)
                    ->maxLength(255),
                Forms\Components\Select::make('class_id')
                    ->live()
                    ->relationship('class', 'name'),
                Forms\Components\Select::make('section_id')
                    ->options(function(Get $get){
                        $classId = $get('class_id');
                        if($classId){
                            return Section::where('class_id', $classId)->pluck('name', 'id')->toArray();
                        };
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('class.name')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('section.name')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                // Tables\Filters\SelectFilter::make('Class')
                //     ->relationship('class', 'name'),
                Tables\Filters\Filter::make('Section')
                    ->form([
                        Select::make('class_id')
                        ->label('Filter By Class')
                        ->placeholder('Select a Class')
                        ->options(
                            Classes::pluck('name', 'id')->toArray(),
                        ),
                        Select::make('section_id')
                        ->label('Filter By Section')
                        ->placeholder('Select a Section')
                        ->options(function(Get $get){
                            $classId = $get('class_id');
                            if($classId){
                                return Section::where('class_id', $classId)->pluck('name', 'id')->toArray();
                            }
                        }),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query->when($data['class_id'], function($query) use ($data){
                            return $query->where('class_id', $data['class_id']);
                        })->when($data['section_id'], function($query) use ($data){
                            return $query->where('section_id', $data['section_id']);
                        });
                    })
            ])
            ->actions([
                Tables\Actions\Action::make('downloadPdf')
                ->url(function(Student $student){
                    return route('student.invoice.generate', $student);
                }),
                Tables\Actions\Action::make('qrCode')
                ->url(function(Student $record){
                    return static::getUrl('qrCode', ['record' => $record]);
                }),
                ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\BulkAction::make('export')
                    ->label('Export to Excel')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(function(Collection $records){
                        return Excel::download(new StudentsExport($records), 'students.xlsx');
                    }),
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
            'index' => Pages\ListStudents::route('/'),
            'create' => Pages\CreateStudent::route('/create'),
            'view' => Pages\ViewStudent::route('/{record}'),
            'edit' => Pages\EditStudent::route('/{record}/edit'),
            'qrCode' => Pages\GenerateQrCode::route('/{record}/qrcode'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
