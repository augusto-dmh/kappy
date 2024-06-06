import { Model, DataTypes } from 'sequelize';

export default class Lesson extends Model {
  static init(sequelize) {
    super.init(
      {
        moduleId: {
          type: DataTypes.INTEGER,
          references: {
            model: 'modules',
            key: 'id',
          },
        },
        name: {
          type: DataTypes.STRING,
          allowNull: false,
        },
        programmingLanguage: {
          type: DataTypes.STRING,
          allowNull: false,
        },
      },
      {
        sequelize,
        modelName: "lesson",
      }
    );
  }
  static associate(models) {
    this.belongsTo(models.module, {
      foreignKey: 'moduleId',
      as: 'module',
    });
    this.hasMany(models.exercise, {
      foreignKey: 'lessonId',
      as: 'exercises',
    });
    this.hasMany(models.lessonDependencies, {
      foreignKey: 'lessonId',
      as: 'dependencies',
    }
    )
  }
}
