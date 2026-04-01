<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\PascalUser;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = PascalUser::class;

    protected static ?string $navigationIcon  = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'User Management';
    protected static ?string $navigationLabel = 'Users';
    protected static ?string $modelLabel      = 'User';
    protected static ?int    $navigationSort  = 1;

    // ── FORM ─────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Identity')
                ->description('Basic account information')
                ->icon('heroicon-o-user')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('full_name')
                        ->label('Full Name')
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('name')
                        ->label('Username')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(120)
                        ->helperText('Unique identifier, used in the URL. Cannot be changed after creation.')
                        ->disabledOn('edit')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('email')
                        ->label('Email Address')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('phone')
                        ->label('Phone')
                        ->tel()
                        ->maxLength(30)
                        ->columnSpan(1),
                ]),

            Forms\Components\Section::make('Account')
                ->description('Role, status, and password')
                ->icon('heroicon-o-shield-check')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('role')
                        ->label('Role')
                        ->options([
                            'user'    => 'User',
                            'manager' => 'Manager',
                            'admin'   => 'Administrator',
                        ])
                        ->required()
                        ->default('user')
                        ->columnSpan(1),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'Active'   => 'Active',
                            'Inactive' => 'Inactive',
                            'Banned'   => 'Banned',
                        ])
                        ->required()
                        ->default('Active')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('password')
                        ->label('Password')
                        ->password()
                        ->revealable()
                        ->required(fn (string $operation) => $operation === 'create')
                        ->minLength(8)
                        ->dehydrateStateUsing(fn ($state) => !empty($state) ? Hash::make($state) : null)
                        ->dehydrated(fn ($state) => filled($state))
                        ->helperText('Leave blank to keep existing password (when editing).')
                        ->columnSpan(1),

                    Forms\Components\FileUpload::make('avatar')
                        ->label('Avatar')
                        ->image()
                        ->imageEditor()
                        ->disk('public')
                        ->directory('avatars')
                        ->maxSize(2048)
                        ->columnSpan(1),
                ]),

            Forms\Components\Section::make('System Info')
                ->description('Read-only audit fields')
                ->icon('heroicon-o-information-circle')
                ->columns(2)
                ->collapsed()
                ->visibleOn('edit')
                ->schema([
                    Forms\Components\Placeholder::make('created_at')
                        ->label('Created')
                        ->content(fn (?PascalUser $record) => $record?->created_at?->diffForHumans()),

                    Forms\Components\Placeholder::make('last_login_at')
                        ->label('Last Login')
                        ->content(fn (?PascalUser $record) => $record?->last_login_at
                            ? $record->last_login_at->diffForHumans() . ' from ' . ($record->last_login_ip ?? 'unknown IP')
                            : 'Never'),
                ]),

        ]);
    }

    // ── TABLE ─────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->label('')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(fn ($record) =>
                        'https://www.gravatar.com/avatar/' . md5(strtolower($record->email)) . '?d=identicon&s=64'
                    )
                    ->width(40)->height(40),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-m-envelope'),

                Tables\Columns\BadgeColumn::make('role')
                    ->colors([
                        'danger'  => 'admin',
                        'warning' => 'manager',
                        'gray'    => 'user',
                    ]),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'Active',
                        'warning' => 'Inactive',
                        'danger'  => 'Banned',
                    ]),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->since()
                    ->sortable()
                    ->placeholder('Never'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->date('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            ->defaultSort('created_at', 'desc')

            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'user'    => 'User',
                        'manager' => 'Manager',
                        'admin'   => 'Administrator',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Active'   => 'Active',
                        'Inactive' => 'Inactive',
                        'Banned'   => 'Banned',
                    ]),

                Tables\Filters\TrashedFilter::make(),
            ])

            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('ban')
                    ->label('Ban')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Ban this user?')
                    ->modalDescription('The user will be immediately logged out and cannot log in.')
                    ->visible(fn (PascalUser $record) =>
                        $record->status !== 'Banned' && $record->role !== 'admin'
                    )
                    ->action(function (PascalUser $record) {
                        $record->update(['status' => 'Banned']);
                        // Revoke all tokens
                        \Illuminate\Support\Facades\DB::table('personal_access_tokens')
                            ->where('tokenable_type', 'pascal_user')
                            ->where('tokenable_id', $record->id)
                            ->delete();

                        Notification::make()
                            ->title("User {$record->full_name} has been banned.")
                            ->danger()
                            ->send();
                    }),

                Tables\Actions\Action::make('unban')
                    ->label('Unban')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (PascalUser $record) => $record->status === 'Banned')
                    ->action(function (PascalUser $record) {
                        $record->update(['status' => 'Active']);
                        Notification::make()
                            ->title("User {$record->full_name} has been unbanned.")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->before(function (PascalUser $record) {
                        if ($record->role === 'admin') {
                            $adminCount = PascalUser::where('role', 'admin')
                                ->where('id', '!=', $record->id)
                                ->count();
                            if ($adminCount === 0) {
                                Notification::make()
                                    ->title('Cannot delete the last administrator.')
                                    ->danger()
                                    ->send();
                                return false;
                            }
                        }
                    }),

                Tables\Actions\RestoreAction::make(),
            ])

            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Set Active')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['status' => 'Active']))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Set Inactive')
                        ->icon('heroicon-o-x-mark')
                        ->action(fn ($records) => $records->each->update(['status' => 'Inactive']))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    // ── PAGES ─────────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    // ── QUERY ─────────────────────────────────────────────────────────────────

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withTrashed();
    }

    public static function canCreate(): bool
    {
        return auth('pascal')->user()?->isAdmin() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth('pascal')->user()?->isAdmin() ?? false;
    }
}
