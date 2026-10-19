// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'get_qrcode_checkin_bloc.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

T _$identity<T>(T value) => value;

final _privateConstructorUsedError = UnsupportedError(
    'It seems like you constructed your class using `MyClass._()`. This constructor is only meant to be used by freezed and you are not supposed to need it nor use it.\nPlease check the documentation here for more information: https://github.com/rrousselGit/freezed#adding-getters-and-methods-to-our-models');

/// @nodoc
mixin _$GetQrcodeCheckinEvent {
  @optionalTypeArgs
  TResult when<TResult extends Object?>({
    required TResult Function() started,
    required TResult Function(String barcode, bool isCheckedIn)
        getQrcodeCheckin,
  }) =>
      throw _privateConstructorUsedError;
  @optionalTypeArgs
  TResult? whenOrNull<TResult extends Object?>({
    TResult? Function()? started,
    TResult? Function(String barcode, bool isCheckedIn)? getQrcodeCheckin,
  }) =>
      throw _privateConstructorUsedError;
  @optionalTypeArgs
  TResult maybeWhen<TResult extends Object?>({
    TResult Function()? started,
    TResult Function(String barcode, bool isCheckedIn)? getQrcodeCheckin,
    required TResult orElse(),
  }) =>
      throw _privateConstructorUsedError;
  @optionalTypeArgs
  TResult map<TResult extends Object?>({
    required TResult Function(_Started value) started,
    required TResult Function(_GetQrcodeCheckin value) getQrcodeCheckin,
  }) =>
      throw _privateConstructorUsedError;
  @optionalTypeArgs
  TResult? mapOrNull<TResult extends Object?>({
    TResult? Function(_Started value)? started,
    TResult? Function(_GetQrcodeCheckin value)? getQrcodeCheckin,
  }) =>
      throw _privateConstructorUsedError;
  @optionalTypeArgs
  TResult maybeMap<TResult extends Object?>({
    TResult Function(_Started value)? started,
    TResult Function(_GetQrcodeCheckin value)? getQrcodeCheckin,
    required TResult orElse(),
  }) =>
      throw _privateConstructorUsedError;
}

/// @nodoc
abstract class $GetQrcodeCheckinEventCopyWith<$Res> {
  factory $GetQrcodeCheckinEventCopyWith(GetQrcodeCheckinEvent value,
          $Res Function(GetQrcodeCheckinEvent) then) =
      _$GetQrcodeCheckinEventCopyWithImpl<$Res, GetQrcodeCheckinEvent>;
}

/// @nodoc
class _$GetQrcodeCheckinEventCopyWithImpl<$Res,
        $Val extends GetQrcodeCheckinEvent>
    implements $GetQrcodeCheckinEventCopyWith<$Res> {
  _$GetQrcodeCheckinEventCopyWithImpl(this._value, this._then);

  // ignore: unused_field
  final $Val _value;
  // ignore: unused_field
  final $Res Function($Val) _then;
}

/// @nodoc
abstract class _$$StartedImplCopyWith<$Res> {
  factory _$$StartedImplCopyWith(
          _$StartedImpl value, $Res Function(_$StartedImpl) then) =
      __$$StartedImplCopyWithImpl<$Res>;
}

/// @nodoc
class __$$StartedImplCopyWithImpl<$Res>
    extends _$GetQrcodeCheckinEventCopyWithImpl<$Res, _$StartedImpl>
    implements _$$StartedImplCopyWith<$Res> {
  __$$StartedImplCopyWithImpl(
      _$StartedImpl _value, $Res Function(_$StartedImpl) _then)
      : super(_value, _then);
}

/// @nodoc

class _$StartedImpl implements _Started {
  const _$StartedImpl();

  @override
  String toString() {
    return 'GetQrcodeCheckinEvent.started()';
  }

  @override
  bool operator ==(Object other) {
    return identical(this, other) ||
        (other.runtimeType == runtimeType && other is _$StartedImpl);
  }

  @override
  int get hashCode => runtimeType.hashCode;

  @override
  @optionalTypeArgs
  TResult when<TResult extends Object?>({
    required TResult Function() started,
    required TResult Function(String barcode, bool isCheckedIn)
        getQrcodeCheckin,
  }) {
    return started();
  }

  @override
  @optionalTypeArgs
  TResult? whenOrNull<TResult extends Object?>({
    TResult? Function()? started,
    TResult? Function(String barcode, bool isCheckedIn)? getQrcodeCheckin,
  }) {
    return started?.call();
  }

  @override
  @optionalTypeArgs
  TResult maybeWhen<TResult extends Object?>({
    TResult Function()? started,
    TResult Function(String barcode, bool isCheckedIn)? getQrcodeCheckin,
    required TResult orElse(),
  }) {
    if (started != null) {
      return started();
    }
    return orElse();
  }

  @override
  @optionalTypeArgs
  TResult map<TResult extends Object?>({
    required TResult Function(_Started value) started,
    required TResult Function(_GetQrcodeCheckin value) getQrcodeCheckin,
  }) {
    return started(this);
  }

  @override
  @optionalTypeArgs
  TResult? mapOrNull<TResult extends Object?>({
    TResult? Function(_Started value)? started,
    TResult? Function(_GetQrcodeCheckin value)? getQrcodeCheckin,
  }) {
    return started?.call(this);
  }

  @override
  @optionalTypeArgs
  TResult maybeMap<TResult extends Object?>({
    TResult Function(_Started value)? started,
    TResult Function(_GetQrcodeCheckin value)? getQrcodeCheckin,
    required TResult orElse(),
  }) {
    if (started != null) {
      return started(this);
    }
    return orElse();
  }
}

abstract class _Started implements GetQrcodeCheckinEvent {
  const factory _Started() = _$StartedImpl;
}

/// @nodoc
abstract class _$$GetQrcodeCheckinImplCopyWith<$Res> {
  factory _$$GetQrcodeCheckinImplCopyWith(_$GetQrcodeCheckinImpl value,
          $Res Function(_$GetQrcodeCheckinImpl) then) =
      __$$GetQrcodeCheckinImplCopyWithImpl<$Res>;
  @useResult
  $Res call({String barcode, bool isCheckedIn});
}

/// @nodoc
class __$$GetQrcodeCheckinImplCopyWithImpl<$Res>
    extends _$GetQrcodeCheckinEventCopyWithImpl<$Res, _$GetQrcodeCheckinImpl>
    implements _$$GetQrcodeCheckinImplCopyWith<$Res> {
  __$$GetQrcodeCheckinImplCopyWithImpl(_$GetQrcodeCheckinImpl _value,
      $Res Function(_$GetQrcodeCheckinImpl) _then)
      : super(_value, _then);

  @pragma('vm:prefer-inline')
  @override
  $Res call({
    Object? barcode = null,
    Object? isCheckedIn = null,
  }) {
    return _then(_$GetQrcodeCheckinImpl(
      null == barcode
          ? _value.barcode
          : barcode // ignore: cast_nullable_to_non_nullable
              as String,
      null == isCheckedIn
          ? _value.isCheckedIn
          : isCheckedIn // ignore: cast_nullable_to_non_nullable
              as bool,
    ));
  }
}

/// @nodoc

class _$GetQrcodeCheckinImpl implements _GetQrcodeCheckin {
  const _$GetQrcodeCheckinImpl(this.barcode, this.isCheckedIn);

  @override
  final String barcode;
  @override
  final bool isCheckedIn;

  @override
  String toString() {
    return 'GetQrcodeCheckinEvent.getQrcodeCheckin(barcode: $barcode, isCheckedIn: $isCheckedIn)';
  }

  @override
  bool operator ==(Object other) {
    return identical(this, other) ||
        (other.runtimeType == runtimeType &&
            other is _$GetQrcodeCheckinImpl &&
            (identical(other.barcode, barcode) || other.barcode == barcode) &&
            (identical(other.isCheckedIn, isCheckedIn) ||
                other.isCheckedIn == isCheckedIn));
  }

  @override
  int get hashCode => Object.hash(runtimeType, barcode, isCheckedIn);

  @JsonKey(ignore: true)
  @override
  @pragma('vm:prefer-inline')
  _$$GetQrcodeCheckinImplCopyWith<_$GetQrcodeCheckinImpl> get copyWith =>
      __$$GetQrcodeCheckinImplCopyWithImpl<_$GetQrcodeCheckinImpl>(
          this, _$identity);

  @override
  @optionalTypeArgs
  TResult when<TResult extends Object?>({
    required TResult Function() started,
    required TResult Function(String barcode, bool isCheckedIn)
        getQrcodeCheckin,
  }) {
    return getQrcodeCheckin(barcode, isCheckedIn);
  }

  @override
  @optionalTypeArgs
  TResult? whenOrNull<TResult extends Object?>({
    TResult? Function()? started,
    TResult? Function(String barcode, bool isCheckedIn)? getQrcodeCheckin,
  }) {
    return getQrcodeCheckin?.call(barcode, isCheckedIn);
  }

  @override
  @optionalTypeArgs
  TResult maybeWhen<TResult extends Object?>({
    TResult Function()? started,
    TResult Function(String barcode, bool isCheckedIn)? getQrcodeCheckin,
    required TResult orElse(),
  }) {
    if (getQrcodeCheckin != null) {
      return getQrcodeCheckin(barcode, isCheckedIn);
    }
    return orElse();
  }

  @override
  @optionalTypeArgs
  TResult map<TResult extends Object?>({
    required TResult Function(_Started value) started,
    required TResult Function(_GetQrcodeCheckin value) getQrcodeCheckin,
  }) {
    return getQrcodeCheckin(this);
  }

  @override
  @optionalTypeArgs
  TResult? mapOrNull<TResult extends Object?>({
    TResult? Function(_Started value)? started,
    TResult? Function(_GetQrcodeCheckin value)? getQrcodeCheckin,
  }) {
    return getQrcodeCheckin?.call(this);
  }

  @override
  @optionalTypeArgs
  TResult maybeMap<TResult extends Object?>({
    TResult Function(_Started value)? started,
    TResult Function(_GetQrcodeCheckin value)? getQrcodeCheckin,
    required TResult orElse(),
  }) {
    if (getQrcodeCheckin != null) {
      return getQrcodeCheckin(this);
    }
    return orElse();
  }
}

abstract class _GetQrcodeCheckin implements GetQrcodeCheckinEvent {
  const factory _GetQrcodeCheckin(
      final String barcode, final bool isCheckedIn) = _$GetQrcodeCheckinImpl;

  String get barcode;
  bool get isCheckedIn;
  @JsonKey(ignore: true)
  _$$GetQrcodeCheckinImplCopyWith<_$GetQrcodeCheckinImpl> get copyWith =>
      throw _privateConstructorUsedError;
}

/// @nodoc
mixin _$GetQrcodeCheckinState {
  @optionalTypeArgs
  TResult when<TResult extends Object?>({
    required TResult Function() initial,
    required TResult Function() loading,
    required TResult Function(String barcode, bool isCheckedIn) success,
  }) =>
      throw _privateConstructorUsedError;
  @optionalTypeArgs
  TResult? whenOrNull<TResult extends Object?>({
    TResult? Function()? initial,
    TResult? Function()? loading,
    TResult? Function(String barcode, bool isCheckedIn)? success,
  }) =>
      throw _privateConstructorUsedError;
  @optionalTypeArgs
  TResult maybeWhen<TResult extends Object?>({
    TResult Function()? initial,
    TResult Function()? loading,
    TResult Function(String barcode, bool isCheckedIn)? success,
    required TResult orElse(),
  }) =>
      throw _privateConstructorUsedError;
  @optionalTypeArgs
  TResult map<TResult extends Object?>({
    required TResult Function(_Initial value) initial,
    required TResult Function(_Loading value) loading,
    required TResult Function(_Success value) success,
  }) =>
      throw _privateConstructorUsedError;
  @optionalTypeArgs
  TResult? mapOrNull<TResult extends Object?>({
    TResult? Function(_Initial value)? initial,
    TResult? Function(_Loading value)? loading,
    TResult? Function(_Success value)? success,
  }) =>
      throw _privateConstructorUsedError;
  @optionalTypeArgs
  TResult maybeMap<TResult extends Object?>({
    TResult Function(_Initial value)? initial,
    TResult Function(_Loading value)? loading,
    TResult Function(_Success value)? success,
    required TResult orElse(),
  }) =>
      throw _privateConstructorUsedError;
}

/// @nodoc
abstract class $GetQrcodeCheckinStateCopyWith<$Res> {
  factory $GetQrcodeCheckinStateCopyWith(GetQrcodeCheckinState value,
          $Res Function(GetQrcodeCheckinState) then) =
      _$GetQrcodeCheckinStateCopyWithImpl<$Res, GetQrcodeCheckinState>;
}

/// @nodoc
class _$GetQrcodeCheckinStateCopyWithImpl<$Res,
        $Val extends GetQrcodeCheckinState>
    implements $GetQrcodeCheckinStateCopyWith<$Res> {
  _$GetQrcodeCheckinStateCopyWithImpl(this._value, this._then);

  // ignore: unused_field
  final $Val _value;
  // ignore: unused_field
  final $Res Function($Val) _then;
}

/// @nodoc
abstract class _$$InitialImplCopyWith<$Res> {
  factory _$$InitialImplCopyWith(
          _$InitialImpl value, $Res Function(_$InitialImpl) then) =
      __$$InitialImplCopyWithImpl<$Res>;
}

/// @nodoc
class __$$InitialImplCopyWithImpl<$Res>
    extends _$GetQrcodeCheckinStateCopyWithImpl<$Res, _$InitialImpl>
    implements _$$InitialImplCopyWith<$Res> {
  __$$InitialImplCopyWithImpl(
      _$InitialImpl _value, $Res Function(_$InitialImpl) _then)
      : super(_value, _then);
}

/// @nodoc

class _$InitialImpl implements _Initial {
  const _$InitialImpl();

  @override
  String toString() {
    return 'GetQrcodeCheckinState.initial()';
  }

  @override
  bool operator ==(Object other) {
    return identical(this, other) ||
        (other.runtimeType == runtimeType && other is _$InitialImpl);
  }

  @override
  int get hashCode => runtimeType.hashCode;

  @override
  @optionalTypeArgs
  TResult when<TResult extends Object?>({
    required TResult Function() initial,
    required TResult Function() loading,
    required TResult Function(String barcode, bool isCheckedIn) success,
  }) {
    return initial();
  }

  @override
  @optionalTypeArgs
  TResult? whenOrNull<TResult extends Object?>({
    TResult? Function()? initial,
    TResult? Function()? loading,
    TResult? Function(String barcode, bool isCheckedIn)? success,
  }) {
    return initial?.call();
  }

  @override
  @optionalTypeArgs
  TResult maybeWhen<TResult extends Object?>({
    TResult Function()? initial,
    TResult Function()? loading,
    TResult Function(String barcode, bool isCheckedIn)? success,
    required TResult orElse(),
  }) {
    if (initial != null) {
      return initial();
    }
    return orElse();
  }

  @override
  @optionalTypeArgs
  TResult map<TResult extends Object?>({
    required TResult Function(_Initial value) initial,
    required TResult Function(_Loading value) loading,
    required TResult Function(_Success value) success,
  }) {
    return initial(this);
  }

  @override
  @optionalTypeArgs
  TResult? mapOrNull<TResult extends Object?>({
    TResult? Function(_Initial value)? initial,
    TResult? Function(_Loading value)? loading,
    TResult? Function(_Success value)? success,
  }) {
    return initial?.call(this);
  }

  @override
  @optionalTypeArgs
  TResult maybeMap<TResult extends Object?>({
    TResult Function(_Initial value)? initial,
    TResult Function(_Loading value)? loading,
    TResult Function(_Success value)? success,
    required TResult orElse(),
  }) {
    if (initial != null) {
      return initial(this);
    }
    return orElse();
  }
}

abstract class _Initial implements GetQrcodeCheckinState {
  const factory _Initial() = _$InitialImpl;
}

/// @nodoc
abstract class _$$LoadingImplCopyWith<$Res> {
  factory _$$LoadingImplCopyWith(
          _$LoadingImpl value, $Res Function(_$LoadingImpl) then) =
      __$$LoadingImplCopyWithImpl<$Res>;
}

/// @nodoc
class __$$LoadingImplCopyWithImpl<$Res>
    extends _$GetQrcodeCheckinStateCopyWithImpl<$Res, _$LoadingImpl>
    implements _$$LoadingImplCopyWith<$Res> {
  __$$LoadingImplCopyWithImpl(
      _$LoadingImpl _value, $Res Function(_$LoadingImpl) _then)
      : super(_value, _then);
}

/// @nodoc

class _$LoadingImpl implements _Loading {
  const _$LoadingImpl();

  @override
  String toString() {
    return 'GetQrcodeCheckinState.loading()';
  }

  @override
  bool operator ==(Object other) {
    return identical(this, other) ||
        (other.runtimeType == runtimeType && other is _$LoadingImpl);
  }

  @override
  int get hashCode => runtimeType.hashCode;

  @override
  @optionalTypeArgs
  TResult when<TResult extends Object?>({
    required TResult Function() initial,
    required TResult Function() loading,
    required TResult Function(String barcode, bool isCheckedIn) success,
  }) {
    return loading();
  }

  @override
  @optionalTypeArgs
  TResult? whenOrNull<TResult extends Object?>({
    TResult? Function()? initial,
    TResult? Function()? loading,
    TResult? Function(String barcode, bool isCheckedIn)? success,
  }) {
    return loading?.call();
  }

  @override
  @optionalTypeArgs
  TResult maybeWhen<TResult extends Object?>({
    TResult Function()? initial,
    TResult Function()? loading,
    TResult Function(String barcode, bool isCheckedIn)? success,
    required TResult orElse(),
  }) {
    if (loading != null) {
      return loading();
    }
    return orElse();
  }

  @override
  @optionalTypeArgs
  TResult map<TResult extends Object?>({
    required TResult Function(_Initial value) initial,
    required TResult Function(_Loading value) loading,
    required TResult Function(_Success value) success,
  }) {
    return loading(this);
  }

  @override
  @optionalTypeArgs
  TResult? mapOrNull<TResult extends Object?>({
    TResult? Function(_Initial value)? initial,
    TResult? Function(_Loading value)? loading,
    TResult? Function(_Success value)? success,
  }) {
    return loading?.call(this);
  }

  @override
  @optionalTypeArgs
  TResult maybeMap<TResult extends Object?>({
    TResult Function(_Initial value)? initial,
    TResult Function(_Loading value)? loading,
    TResult Function(_Success value)? success,
    required TResult orElse(),
  }) {
    if (loading != null) {
      return loading(this);
    }
    return orElse();
  }
}

abstract class _Loading implements GetQrcodeCheckinState {
  const factory _Loading() = _$LoadingImpl;
}

/// @nodoc
abstract class _$$SuccessImplCopyWith<$Res> {
  factory _$$SuccessImplCopyWith(
          _$SuccessImpl value, $Res Function(_$SuccessImpl) then) =
      __$$SuccessImplCopyWithImpl<$Res>;
  @useResult
  $Res call({String barcode, bool isCheckedIn});
}

/// @nodoc
class __$$SuccessImplCopyWithImpl<$Res>
    extends _$GetQrcodeCheckinStateCopyWithImpl<$Res, _$SuccessImpl>
    implements _$$SuccessImplCopyWith<$Res> {
  __$$SuccessImplCopyWithImpl(
      _$SuccessImpl _value, $Res Function(_$SuccessImpl) _then)
      : super(_value, _then);

  @pragma('vm:prefer-inline')
  @override
  $Res call({
    Object? barcode = null,
    Object? isCheckedIn = null,
  }) {
    return _then(_$SuccessImpl(
      null == barcode
          ? _value.barcode
          : barcode // ignore: cast_nullable_to_non_nullable
              as String,
      null == isCheckedIn
          ? _value.isCheckedIn
          : isCheckedIn // ignore: cast_nullable_to_non_nullable
              as bool,
    ));
  }
}

/// @nodoc

class _$SuccessImpl implements _Success {
  const _$SuccessImpl(this.barcode, this.isCheckedIn);

  @override
  final String barcode;
  @override
  final bool isCheckedIn;

  @override
  String toString() {
    return 'GetQrcodeCheckinState.success(barcode: $barcode, isCheckedIn: $isCheckedIn)';
  }

  @override
  bool operator ==(Object other) {
    return identical(this, other) ||
        (other.runtimeType == runtimeType &&
            other is _$SuccessImpl &&
            (identical(other.barcode, barcode) || other.barcode == barcode) &&
            (identical(other.isCheckedIn, isCheckedIn) ||
                other.isCheckedIn == isCheckedIn));
  }

  @override
  int get hashCode => Object.hash(runtimeType, barcode, isCheckedIn);

  @JsonKey(ignore: true)
  @override
  @pragma('vm:prefer-inline')
  _$$SuccessImplCopyWith<_$SuccessImpl> get copyWith =>
      __$$SuccessImplCopyWithImpl<_$SuccessImpl>(this, _$identity);

  @override
  @optionalTypeArgs
  TResult when<TResult extends Object?>({
    required TResult Function() initial,
    required TResult Function() loading,
    required TResult Function(String barcode, bool isCheckedIn) success,
  }) {
    return success(barcode, isCheckedIn);
  }

  @override
  @optionalTypeArgs
  TResult? whenOrNull<TResult extends Object?>({
    TResult? Function()? initial,
    TResult? Function()? loading,
    TResult? Function(String barcode, bool isCheckedIn)? success,
  }) {
    return success?.call(barcode, isCheckedIn);
  }

  @override
  @optionalTypeArgs
  TResult maybeWhen<TResult extends Object?>({
    TResult Function()? initial,
    TResult Function()? loading,
    TResult Function(String barcode, bool isCheckedIn)? success,
    required TResult orElse(),
  }) {
    if (success != null) {
      return success(barcode, isCheckedIn);
    }
    return orElse();
  }

  @override
  @optionalTypeArgs
  TResult map<TResult extends Object?>({
    required TResult Function(_Initial value) initial,
    required TResult Function(_Loading value) loading,
    required TResult Function(_Success value) success,
  }) {
    return success(this);
  }

  @override
  @optionalTypeArgs
  TResult? mapOrNull<TResult extends Object?>({
    TResult? Function(_Initial value)? initial,
    TResult? Function(_Loading value)? loading,
    TResult? Function(_Success value)? success,
  }) {
    return success?.call(this);
  }

  @override
  @optionalTypeArgs
  TResult maybeMap<TResult extends Object?>({
    TResult Function(_Initial value)? initial,
    TResult Function(_Loading value)? loading,
    TResult Function(_Success value)? success,
    required TResult orElse(),
  }) {
    if (success != null) {
      return success(this);
    }
    return orElse();
  }
}

abstract class _Success implements GetQrcodeCheckinState {
  const factory _Success(final String barcode, final bool isCheckedIn) =
      _$SuccessImpl;

  String get barcode;
  bool get isCheckedIn;
  @JsonKey(ignore: true)
  _$$SuccessImplCopyWith<_$SuccessImpl> get copyWith =>
      throw _privateConstructorUsedError;
}
